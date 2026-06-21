<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Court;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private string $secretKey;
    private string $baseUrl = 'https://api.paymongo.com/v1';

    public function __construct()
    {
        $this->secretKey = config('services.paymongo.secret_key');
    }

    public function createSource(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'court_id'        => 'required|exists:courts,id',
            'booking_date'    => 'required|date|after_or_equal:today',
            'time_slot_start' => 'required|string',
            'time_slot_end'   => 'required|string',
            'payment_method'  => 'required|in:gcash,maya',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $court = Court::findOrFail($request->court_id);

        if (!$court->is_active) {
            return response()->json(['message' => 'This court is currently closed.'], 422);
        }

        $conflictCheck = $this->validateBookingTime($court, $request);
        if ($conflictCheck !== null) {
            return $conflictCheck;
        }

        try {
            $start         = Carbon::createFromFormat('g:i A', $request->time_slot_start);
            $end           = Carbon::createFromFormat('g:i A', $request->time_slot_end);
            $durationHours = (int) $start->diffInHours($end);
        } catch (\Exception $e) {
            $durationHours = 1;
        }

        $totalPrice  = $court->price_per_hour * max($durationHours, 1);
        $amountCents = (int) round($totalPrice * 100);

        $bookingCode   = 'PP-' . date('Y') . '-' . date('md') . '-' . strtoupper(Str::random(4));
        $bookingTempId = Str::uuid()->toString();

        $response = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/sources", [
                'data' => [
                    'attributes' => [
                        'amount'   => $amountCents,
                        'currency' => 'PHP',
                        'type'     => $request->payment_method,
                        'redirect' => [
                            'success' => url('/api/payments/callback/success?temp_id=' . $bookingTempId),
                            'failed'  => url('/api/payments/callback/failed?temp_id=' . $bookingTempId),
                        ],
                        'billing' => [
                            'name'  => $request->user()->name,
                            'email' => $request->user()->email,
                            'phone' => $request->user()->phone ?? '',
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Failed to create payment source. Please try again.',
                'detail'  => $response->json(),
                'status'  => $response->status(),
            ], 500);
        }

        $source = $response->json('data');

        $booking = Booking::create([
            'user_id'            => $request->user()->id,
            'court_id'           => $request->court_id,
            'booking_date'       => $request->booking_date,
            'time_slot_start'    => $request->time_slot_start,
            'time_slot_end'      => $request->time_slot_end,
            'duration_hours'     => $durationHours,
            'total_price'        => $totalPrice,
            'booking_code'       => $bookingCode,
            'status'             => 'pending',
            'payment_method'     => $request->payment_method,
            'payment_status'     => 'pending',
            'paymongo_source_id' => $source['id'],
        ]);

        return response()->json([
            'source_id'       => $source['id'],
            'checkout_url'    => $source['attributes']['redirect']['checkout_url'],
            'booking_temp_id' => (string) $booking->id,
            'amount'          => $totalPrice,
        ]);
    }

    private function validateBookingTime(Court $court, Request $request)
    {
        // Validate against court's operating hours
        $courtOpenTime = $court->time_slots[0]['start'] ?? null;
        $courtCloseTime = $court->time_slots[0]['end'] ?? null;

        try {
            $requestedStart = Carbon::createFromFormat('g:i A', $request->time_slot_start);
            $requestedEnd = Carbon::createFromFormat('g:i A', $request->time_slot_end);

            if ($courtOpenTime && $courtCloseTime) {
                $openTime = Carbon::createFromFormat('g:i A', $courtOpenTime);
                $closeTime = Carbon::createFromFormat('g:i A', $courtCloseTime);

                if ($requestedStart->lt($openTime) || $requestedEnd->gt($closeTime)) {
                    return response()->json([
                        'error_code' => 'outside_hours',
                        'message' => "Booking time must be within court operating hours ({$courtOpenTime} - {$courtCloseTime}).",
                    ], 422);
                }
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid time format.'], 422);
        }

        // Check for overlapping paid bookings
        $overlappingBooking = Booking::where('court_id', $request->court_id)
            ->where('booking_date', $request->booking_date)
            ->whereNotIn('status', ['cancelled'])
            ->where('payment_status', 'paid')
            ->where(function ($query) use ($requestedStart, $requestedEnd) {
                $query->where(function ($q) use ($requestedStart, $requestedEnd) {
                    $q->where('time_slot_start', '<=', $requestedStart->format('g:i A'))
                      ->where('time_slot_end', '>', $requestedStart->format('g:i A'));
                })->orWhere(function ($q) use ($requestedStart, $requestedEnd) {
                    $q->where('time_slot_start', '<', $requestedEnd->format('g:i A'))
                      ->where('time_slot_end', '>=', $requestedEnd->format('g:i A'));
                })->orWhere(function ($q) use ($requestedStart, $requestedEnd) {
                    $q->where('time_slot_start', '>=', $requestedStart->format('g:i A'))
                      ->where('time_slot_end', '<=', $requestedEnd->format('g:i A'));
                });
            })
            ->exists();

        if ($overlappingBooking) {
            return response()->json([
                'error_code' => 'slot_taken',
                'message' => 'This time slot is already booked. Please choose another.',
            ], 409);
        }

        // Check user conflict
        $userConflict = Booking::where('user_id', $request->user()->id)
            ->where('booking_date', $request->booking_date)
            ->whereNotIn('status', ['cancelled'])
            ->where('payment_status', 'paid')
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

        return null;
    }

    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'source_id'       => 'required|string',
            'booking_temp_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = Booking::where('id', $request->booking_temp_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        if ($booking->payment_status === 'paid') {
            return response()->json(['status' => 'paid', 'booking' => $booking]);
        }

        $response = Http::withBasicAuth($this->secretKey, '')
            ->get("{$this->baseUrl}/sources/{$request->source_id}");

        if (!$response->successful()) {
            return response()->json(['message' => 'Could not verify payment.'], 500);
        }

        $source = $response->json('data');
        $sourceStatus = $source['attributes']['status'] ?? 'pending';

        if ($sourceStatus === 'chargeable') {
            $chargeResponse = Http::withBasicAuth($this->secretKey, '')
                ->post("{$this->baseUrl}/payments", [
                    'data' => [
                        'attributes' => [
                            'amount'      => (int) round($booking->total_price * 100),
                            'currency'    => 'PHP',
                            'source'      => [
                                'id'   => $source['id'],
                                'type' => 'source',
                            ],
                            'description' => "Pickaball Court Booking - {$booking->booking_code}",
                        ],
                    ],
                ]);

            if ($chargeResponse->successful()) {
                $payment = $chargeResponse->json('data');
                $paymentStatus = $payment['attributes']['status'] ?? 'pending';

                if ($paymentStatus === 'paid') {
                    // Check for overlapping bookings BEFORE confirming (first-payment-wins)
                    $requestedStart = Carbon::createFromFormat('g:i A', $booking->time_slot_start);
                    $requestedEnd = Carbon::createFromFormat('g:i A', $booking->time_slot_end);

                    $overlappingBooking = Booking::where('court_id', $booking->court_id)
                        ->where('booking_date', $booking->booking_date)
                        ->where('status', '!=', 'cancelled')
                        ->where('payment_status', 'paid')
                        ->where('id', '!=', $booking->id)
                        ->where(function ($query) use ($requestedStart, $requestedEnd) {
                            $query->where(function ($q) use ($requestedStart, $requestedEnd) {
                                $q->where('time_slot_start', '<=', $requestedStart->format('g:i A'))
                                  ->where('time_slot_end', '>', $requestedStart->format('g:i A'));
                            })->orWhere(function ($q) use ($requestedStart, $requestedEnd) {
                                $q->where('time_slot_start', '<', $requestedEnd->format('g:i A'))
                                  ->where('time_slot_end', '>=', $requestedEnd->format('g:i A'));
                            })->orWhere(function ($q) use ($requestedStart, $requestedEnd) {
                                $q->where('time_slot_start', '>=', $requestedStart->format('g:i A'))
                                  ->where('time_slot_end', '<=', $requestedEnd->format('g:i A'));
                            });
                        })
                        ->first();

                    if ($overlappingBooking) {
                        // Conflict detected - cancel this booking and refund
                        $booking->update([
                            'status' => 'cancelled',
                            'payment_status' => 'refunded',
                        ]);

                        // Attempt refund via PayMongo
                        try {
                            Http::withBasicAuth($this->secretKey, '')
                                ->post("{$this->baseUrl}/payments/{$payment['id']}/refund", [
                                    'data' => [
                                        'attributes' => [
                                            'amount' => (int) round($booking->total_price * 100),
                                            'reason' => 'Time slot conflict - another user secured the booking first',
                                        ],
                                    ],
                                ]);
                        } catch (\Exception $e) {
                            // Log refund failure but still cancel booking
                            \Log::error('Refund failed for booking ' . $booking->id . ': ' . $e->getMessage());
                        }

                        return response()->json([
                            'status' => 'conflict',
                            'message' => 'This time slot was already booked by another user. Your payment has been refunded.',
                            'booking' => $booking->fresh(),
                        ], 409);
                    }

                    // No conflict - confirm the booking
                    $booking->update([
                        'payment_status'      => 'paid',
                        'paymongo_payment_id' => $payment['id'],
                        'status'              => 'confirmed',
                    ]);

                    $booking->load('court');
                    app(NotificationService::class)->notifyBookingConfirmed($booking);
                    app(NotificationService::class)->notifyOwnerBookingConfirmed($booking);

                    return response()->json(['status' => 'paid', 'booking' => $booking->fresh()]);
                }
            }
        }

        return response()->json([
            'status'  => $sourceStatus,
            'message' => 'Payment not yet completed.',
        ]);
    }

    public function createQrPh(Request $request)
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

        $conflictCheck = $this->validateBookingTime($court, $request);
        if ($conflictCheck !== null) {
            return $conflictCheck;
        }

        try {
            $start         = Carbon::createFromFormat('g:i A', $request->time_slot_start);
            $end           = Carbon::createFromFormat('g:i A', $request->time_slot_end);
            $durationHours = (int) $start->diffInHours($end);
        } catch (\Exception $e) {
            $durationHours = 1;
        }

        $totalPrice  = $court->price_per_hour * max($durationHours, 1);
        $amountCents = (int) round($totalPrice * 100);
        $bookingCode = 'PP-' . date('Y') . '-' . date('md') . '-' . strtoupper(Str::random(4));

        // Step 1: Create Payment Intent
        $intentResponse = Http::withBasicAuth($this->secretKey, '')
            ->post("{$this->baseUrl}/payment_intents", [
                'data' => [
                    'attributes' => [
                        'amount'                 => $amountCents,
                        'currency'               => 'PHP',
                        'payment_method_allowed' => ['qrph'],
                        'description'            => "Pickaball - {$court->name} - {$bookingCode}",
                    ],
                ],
            ]);

        if (!$intentResponse->successful()) {
            return response()->json(['message' => 'Failed to create payment intent.', 'detail' => $intentResponse->json()], 500);
        }

        $intent    = $intentResponse->json('data');
        $intentId  = $intent['id'];
        $clientKey = $intent['attributes']['client_key'];

        // Step 2: Create QR PH Payment Method (using public key)
        $publicKey = config('services.paymongo.public_key');
        $pmResponse = Http::withBasicAuth($publicKey, '')
            ->post("{$this->baseUrl}/payment_methods", [
                'data' => [
                    'attributes' => [
                        'type' => 'qrph',
                    ],
                ],
            ]);

        if (!$pmResponse->successful()) {
            return response()->json(['message' => 'Failed to create QR payment method.', 'detail' => $pmResponse->json()], 500);
        }

        $paymentMethodId = $pmResponse->json('data.id');

        // Step 3: Attach Payment Method to Intent
        $attachResponse = Http::withBasicAuth($publicKey, '')
            ->post("{$this->baseUrl}/payment_intents/{$intentId}/attach", [
                'data' => [
                    'attributes' => [
                        'payment_method' => $paymentMethodId,
                        'client_key'     => $clientKey,
                    ],
                ],
            ]);

        if (!$attachResponse->successful()) {
            return response()->json(['message' => 'Failed to attach QR payment method.', 'detail' => $attachResponse->json()], 500);
        }

        $attachedIntent = $attachResponse->json('data');
        $qrImageUrl     = $attachedIntent['attributes']['next_action']['code']['image_url'] ?? null;

        if (!$qrImageUrl) {
            return response()->json(['message' => 'QR code not returned by PayMongo.', 'detail' => $attachedIntent], 500);
        }

        // Create pending booking
        $booking = Booking::create([
            'user_id'            => $request->user()->id,
            'court_id'           => $request->court_id,
            'booking_date'       => $request->booking_date,
            'time_slot_start'    => $request->time_slot_start,
            'time_slot_end'      => $request->time_slot_end,
            'duration_hours'     => $durationHours,
            'total_price'        => $totalPrice,
            'booking_code'       => $bookingCode,
            'status'             => 'pending',
            'payment_method'     => 'qrph',
            'payment_status'     => 'pending',
            'paymongo_source_id' => $intentId,
        ]);

        return response()->json([
            'qr_image_url'    => $qrImageUrl,
            'intent_id'       => $intentId,
            'client_key'      => $clientKey,
            'booking_temp_id' => (string) $booking->id,
            'amount'          => $totalPrice,
            'expires_in'      => 1800,
        ]);
    }

    public function verifyIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'intent_id'       => 'required|string',
            'client_key'      => 'required|string',
            'booking_temp_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = Booking::where('id', $request->booking_temp_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        if ($booking->payment_status === 'paid') {
            return response()->json(['status' => 'succeeded', 'booking' => $booking]);
        }

        $publicKey = config('services.paymongo.public_key');
        $response  = Http::withBasicAuth($publicKey, '')
            ->get("{$this->baseUrl}/payment_intents/{$request->intent_id}?client_key={$request->client_key}");

        if (!$response->successful()) {
            return response()->json(['message' => 'Could not verify payment intent.'], 500);
        }

        $intentStatus = $response->json('data.attributes.status');

        if ($intentStatus === 'succeeded') {
            $paymentId = $response->json('data.attributes.payments.0.id');

            // Check for overlapping bookings BEFORE confirming (first-payment-wins)
            $requestedStart = Carbon::createFromFormat('g:i A', $booking->time_slot_start);
            $requestedEnd = Carbon::createFromFormat('g:i A', $booking->time_slot_end);

            $overlappingBooking = Booking::where('court_id', $booking->court_id)
                ->where('booking_date', $booking->booking_date)
                ->where('status', '!=', 'cancelled')
                ->where('payment_status', 'paid')
                ->where('id', '!=', $booking->id)
                ->where(function ($query) use ($requestedStart, $requestedEnd) {
                    $query->where(function ($q) use ($requestedStart, $requestedEnd) {
                        $q->where('time_slot_start', '<=', $requestedStart->format('g:i A'))
                          ->where('time_slot_end', '>', $requestedStart->format('g:i A'));
                    })->orWhere(function ($q) use ($requestedStart, $requestedEnd) {
                        $q->where('time_slot_start', '<', $requestedEnd->format('g:i A'))
                          ->where('time_slot_end', '>=', $requestedEnd->format('g:i A'));
                    })->orWhere(function ($q) use ($requestedStart, $requestedEnd) {
                        $q->where('time_slot_start', '>=', $requestedStart->format('g:i A'))
                          ->where('time_slot_end', '<=', $requestedEnd->format('g:i A'));
                    });
                })
                ->first();

            if ($overlappingBooking) {
                // Conflict detected - cancel this booking and refund
                $booking->update([
                    'status' => 'cancelled',
                    'payment_status' => 'refunded',
                ]);

                // Attempt refund via PayMongo
                try {
                    Http::withBasicAuth($this->secretKey, '')
                        ->post("{$this->baseUrl}/payments/{$paymentId}/refund", [
                            'data' => [
                                'attributes' => [
                                    'amount' => (int) round($booking->total_price * 100),
                                    'reason' => 'Time slot conflict - another user secured the booking first',
                                ],
                            ],
                        ]);
                } catch (\Exception $e) {
                    // Log refund failure but still cancel booking
                    \Log::error('Refund failed for booking ' . $booking->id . ': ' . $e->getMessage());
                }

                return response()->json([
                    'status' => 'conflict',
                    'message' => 'This time slot was already booked by another user. Your payment has been refunded.',
                    'booking' => $booking->fresh(),
                ], 409);
            }

            // No conflict - confirm the booking
            $booking->update([
                'payment_status'      => 'paid',
                'paymongo_payment_id' => $paymentId,
                'status'              => 'confirmed',
            ]);

            $booking->load('court');
            app(NotificationService::class)->notifyBookingConfirmed($booking);

            return response()->json(['status' => 'succeeded', 'booking' => $booking->fresh()]);
        }

        return response()->json(['status' => $intentStatus]);
    }

    public function callbackSuccess(Request $request)
    {
        return response('<html><body><h2>Payment successful! You can return to the app.</h2></body></html>', 200)
            ->header('Content-Type', 'text/html');
    }

    public function callbackFailed(Request $request)
    {
        return response('<html><body><h2>Payment failed or cancelled. Please return to the app and try again.</h2></body></html>', 200)
            ->header('Content-Type', 'text/html');
    }
}
