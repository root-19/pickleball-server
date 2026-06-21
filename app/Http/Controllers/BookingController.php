<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Court;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    const PLATFORM_FEE = 0.10;

    public function getCourtBookedSlots(Request $request, $courtId)
    {
        $date = $request->query('date', now()->toDateString());

        $bookings = Booking::where('court_id', $courtId)
            ->where('booking_date', $date)
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'paid')
            ->get(['time_slot_start', 'time_slot_end']);

        // Return full booking ranges for conflict detection
        $bookedSlots = $bookings->map(function ($booking) {
            return [
                'start' => $booking->time_slot_start,
                'end' => $booking->time_slot_end,
            ];
        })->toArray();

        return response()->json(['booked_slots' => $bookedSlots]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'court_id'        => 'required|exists:courts,id',
            'booking_date'    => 'required|date|after_or_equal:today',
            'time_slot_start' => 'required|string',
            'time_slot_end'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $court = Court::findOrFail($request->court_id);

        if (!$court->is_active) {
            return response()->json(['message' => 'This court is currently closed.'], 422);
        }

        // Validate against court's opening/closing hours
        $courtOpenTime = $court->time_slots[0]['start'] ?? null;
        $courtCloseTime = $court->time_slots[0]['end'] ?? null;

        if ($courtOpenTime && $courtCloseTime) {
            try {
                $requestedStart = Carbon::createFromFormat('g:i A', $request->time_slot_start);
                $requestedEnd = Carbon::createFromFormat('g:i A', $request->time_slot_end);
                $openTime = Carbon::createFromFormat('g:i A', $courtOpenTime);
                $closeTime = Carbon::createFromFormat('g:i A', $courtCloseTime);

                if ($requestedStart->lt($openTime) || $requestedEnd->gt($closeTime)) {
                    return response()->json([
                        'error_code' => 'outside_hours',
                        'message' => "Booking time must be within court operating hours ({$courtOpenTime} - {$courtCloseTime}).",
                    ], 422);
                }
            } catch (\Exception $e) {
                // If time parsing fails, skip validation
            }
        }

        // Check for overlapping bookings (conflict detection)
        $requestedStart = Carbon::createFromFormat('g:i A', $request->time_slot_start);
        $requestedEnd = Carbon::createFromFormat('g:i A', $request->time_slot_end);

        $overlappingBooking = Booking::where('court_id', $request->court_id)
            ->where('booking_date', $request->booking_date)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($requestedStart, $requestedEnd) {
                $query->where(function ($q) use ($requestedStart, $requestedEnd) {
                    // New booking starts during an existing booking
                    $q->where('time_slot_start', '<=', $requestedStart->format('g:i A'))
                      ->where('time_slot_end', '>', $requestedStart->format('g:i A'));
                })->orWhere(function ($q) use ($requestedStart, $requestedEnd) {
                    // New booking ends during an existing booking
                    $q->where('time_slot_start', '<', $requestedEnd->format('g:i A'))
                      ->where('time_slot_end', '>=', $requestedEnd->format('g:i A'));
                })->orWhere(function ($q) use ($requestedStart, $requestedEnd) {
                    // New booking completely covers an existing booking
                    $q->where('time_slot_start', '>=', $requestedStart->format('g:i A'))
                      ->where('time_slot_end', '<=', $requestedEnd->format('g:i A'));
                });
            })
            ->first();

        if ($overlappingBooking) {
            return response()->json([
                'error_code' => 'slot_taken',
                'message' => 'This time slot is already booked. Please choose another.',
            ], 409);
        }

        // Check user conflict
        $userConflict = Booking::where('user_id', $request->user()->id)
            ->where('booking_date', $request->booking_date)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($requestedStart, $requestedEnd) {
                $query->where('time_slot_start', '<', $requestedEnd->format('g:i A'))
                      ->where('time_slot_end', '>', $requestedStart->format('g:i A'));
            })
            ->exists();

        if ($userConflict) {
            return response()->json([
                'error_code' => 'user_conflict',
                'message' => 'You already have a booking during this time.',
            ], 409);
        }

        try {
            $durationHours = (int) $requestedStart->diffInHours($requestedEnd);
        } catch (\Exception $e) {
            $durationHours = 1;
        }

        $totalPrice  = $court->price_per_hour * max($durationHours, 1);
        $bookingCode = 'PP-' . date('Y') . '-' . date('md') . '-' . strtoupper(Str::random(4));

        $booking = Booking::create([
            'user_id'         => $request->user()->id,
            'court_id'        => $request->court_id,
            'booking_date'    => $request->booking_date,
            'time_slot_start' => $request->time_slot_start,
            'time_slot_end'   => $request->time_slot_end,
            'duration_hours'  => $durationHours,
            'total_price'     => $totalPrice,
            'booking_code'    => $bookingCode,
            'status'          => 'confirmed',
        ]);

        $booking->load('court');
        app(NotificationService::class)->notifyBookingConfirmed($booking);

        return response()->json([
            'booking' => $booking,
            'court'   => [
                'name'     => $court->name,
                'location' => $court->location,
                'image'    => $court->court_image ? url('storage/' . $court->court_image) : null,
            ],
        ], 201);
    }

    public function ownerEarnings(Request $request)
    {
        $ownerId   = $request->user()->id;
        $weekStart = $request->query('week_start')
            ? \Carbon\Carbon::parse($request->query('week_start'))->startOfWeek(\Carbon\Carbon::MONDAY)
            : \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::MONDAY);
        $weekEnd   = $weekStart->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);

        $courtIds  = \App\Models\Court::where('user_id', $ownerId)->pluck('id');

        $bookings = Booking::whereIn('court_id', $courtIds)
            ->whereBetween('booking_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->with('court')
            ->get();

        // Daily breakdown
        $fee   = self::PLATFORM_FEE;
        $net   = fn($amount) => round((float) $amount * (1 - $fee), 2);

        $daily = [];
        $days  = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
        for ($i = 0; $i < 7; $i++) {
            $d    = $weekStart->copy()->addDays($i);
            $dayB = $bookings->filter(fn($b) => $b->booking_date->toDateString() === $d->toDateString());
            $daily[] = [
                'date'     => $d->toDateString(),
                'day'      => $days[$i],
                'revenue'  => $net($dayB->sum('total_price')),
                'bookings' => $dayB->count(),
                'hours'    => (float) $dayB->sum('duration_hours'),
            ];
        }

        // Top courts
        $topCourts = $bookings->groupBy('court_id')->map(function ($group) use ($net) {
            $court = $group->first()->court;
            return [
                'court_id'   => $court->id,
                'court_name' => $court->name,
                'revenue'    => $net($group->sum('total_price')),
                'bookings'   => $group->count(),
                'hours'      => (float) $group->sum('duration_hours'),
            ];
        })->values()->sortByDesc('revenue')->values();

        // Previous week
        $prevStart = $weekStart->copy()->subWeek();
        $prevEnd   = $weekEnd->copy()->subWeek();
        $prevRevenue = Booking::whereIn('court_id', $courtIds)
            ->whereBetween('booking_date', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->sum('total_price');

        return response()->json([
            'week_start'        => $weekStart->toDateString(),
            'week_end'          => $weekEnd->toDateString(),
            'total_revenue'     => $net($bookings->sum('total_price')),
            'total_bookings'    => $bookings->count(),
            'total_hours'       => (float) $bookings->sum('duration_hours'),
            'prev_week_revenue' => $net($prevRevenue),
            'platform_fee_pct'  => self::PLATFORM_FEE * 100,
            'daily'             => $daily,
            'top_courts'        => $topCourts,
        ]);
    }

    public function ownerBookings(Request $request)
    {
        $ownerId  = $request->user()->id;
        $date     = $request->query('date');

        $courtIds = \App\Models\Court::where('user_id', $ownerId)->pluck('id');

        $query = Booking::with(['court', 'user'])
            ->whereIn('court_id', $courtIds)
            ->orderBy('booking_date', 'desc')
            ->orderBy('time_slot_start', 'asc');

        if ($date) {
            $query->where('booking_date', $date);
        }

        $fee      = self::PLATFORM_FEE;
        $bookings = $query->get()->map(function ($b) use ($fee) {
            $gross = (float) $b->total_price;
            return [
                'id'              => $b->id,
                'booking_code'    => $b->booking_code,
                'booking_date'    => $b->booking_date,
                'time_slot_start' => $b->time_slot_start,
                'time_slot_end'   => $b->time_slot_end,
                'duration_hours'  => $b->duration_hours,
                'total_price'     => $gross,
                'net_earnings'    => round($gross * (1 - $fee), 2),
                'platform_fee'    => round($gross * $fee, 2),
                'status'          => $b->status,
                'player_name'     => $b->user->name ?? 'Unknown',
                'player_image'    => $b->user->profile_image ?? null,
                'court_name'      => $b->court->name ?? '',
                'court_image'     => $b->court->court_image
                    ? url('storage/' . $b->court->court_image) : null,
            ];
        });

        return response()->json($bookings);
    }

    public function ownerStats(Request $request)
    {
        $ownerId  = $request->user()->id;
        $today    = now()->toDateString();

        $courtIds = \App\Models\Court::where('user_id', $ownerId)->pluck('id');

        $todayBookings = Booking::whereIn('court_id', $courtIds)
            ->where('booking_date', $today)
            ->where('status', '!=', 'cancelled')
            ->get();

        $upcoming = Booking::with(['court', 'user'])
            ->whereIn('court_id', $courtIds)
            ->where('booking_date', '>=', $today)
            ->where('status', '!=', 'cancelled')
            ->orderBy('booking_date')
            ->orderBy('time_slot_start')
            ->limit(10)
            ->get()
            ->map(function ($b) {
                return [
                    'id'              => $b->id,
                    'booking_date'    => $b->booking_date,
                    'time_slot_start' => $b->time_slot_start,
                    'time_slot_end'   => $b->time_slot_end,
                    'duration_hours'  => $b->duration_hours,
                    'total_price'     => $b->total_price,
                    'status'          => $b->status,
                    'booking_code'    => $b->booking_code,
                    'player_name'     => $b->user->name ?? 'Unknown',
                    'player_image'    => $b->user->profile_image
                        ? url('storage/' . $b->user->profile_image) : null,
                    'court_name'      => $b->court->name ?? '',
                ];
            });

        $net = fn($amount) => round((float) $amount * (1 - self::PLATFORM_FEE), 2);

        return response()->json([
            'today_bookings'   => $todayBookings->count(),
            'today_revenue'    => $net($todayBookings->sum('total_price')),
            'today_hours'      => $todayBookings->sum('duration_hours'),
            'unique_players'   => $todayBookings->pluck('user_id')->unique()->count(),
            'platform_fee_pct' => self::PLATFORM_FEE * 100,
            'upcoming'         => $upcoming,
        ]);
    }

    public function index(Request $request)
    {
        $bookings = Booking::with('court')
            ->where('user_id', $request->user()->id)
            ->orderBy('booking_date', 'desc')
            ->orderBy('time_slot_start', 'desc')
            ->get()
            ->map(function ($b) {
                $data              = $b->toArray();
                $data['court_name']     = $b->court->name ?? '';
                $data['court_location'] = $b->court->location ?? '';
                $data['court_image']    = $b->court->court_image
                    ? url('storage/' . $b->court->court_image) : null;
                return $data;
            });

        return response()->json($bookings);
    }
}
