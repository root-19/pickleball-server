<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Court;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CalendarController extends Controller
{
    /**
     * Get bookings for a week grouped by court and day for the authenticated owner.
     */
    public function ownerWeek(Request $request)
    {
        $date = $request->query('date', Carbon::today()->toDateString());
        $startOfWeek = Carbon::parse($date)->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $startOfWeek->copy()->endOfWeek(Carbon::SUNDAY);

        $courtIds = Court::where('user_id', $request->user()->id)->pluck('id');

        $bookings = Booking::with('user', 'court')
            ->whereIn('court_id', $courtIds)
            ->whereBetween('booking_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        $courts = Court::where('user_id', $request->user()->id)
            ->get();

        $result = $courts->map(function ($court) use ($bookings) {
            $courtBookings = $bookings->where('court_id', $court->id)->values();

            return [
                'court_id'   => $court->id,
                'court_name' => $court->name,
                'bookings'   => $courtBookings->map(function ($booking) {
                    return [
                        'id'          => $booking->id,
                        'date'        => $booking->booking_date->toDateString(),
                        'start_time'  => $booking->time_slot_start,
                        'end_time'    => $booking->time_slot_end,
                        'status'      => $booking->status,
                        'player_name' => $booking->user->name ?? 'Unknown',
                        'total_price' => $booking->total_price,
                    ];
                }),
            ];
        });

        return response()->json([
            'week_start' => $startOfWeek->toDateString(),
            'week_end' => $endOfWeek->toDateString(),
            'courts' => $result,
        ]);
    }
}
