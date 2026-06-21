<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingReview;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => 'required|integer|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'result' => 'required|in:win,lose',
            'notification_id' => 'nullable|integer|exists:notifications,id',
        ]);

        $booking = Booking::where('id', $validated['booking_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $existing = BookingReview::where('booking_id', $booking->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You already reviewed this booking.',
                'review' => $existing,
            ], 422);
        }

        $review = BookingReview::create([
            'booking_id' => $booking->id,
            'user_id' => $request->user()->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'result' => $validated['result'],
        ]);

        $booking->load('court');
        app(NotificationService::class)->notifyOwnerReviewAdded($booking, $validated['rating']);

        if (!empty($validated['notification_id'])) {
            $notification = Notification::where('user_id', $request->user()->id)
                ->find($validated['notification_id']);
            $notification?->markAsRead();
        }

        return response()->json(['review' => $review], 201);
    }

    public function show(Request $request, int $bookingId): JsonResponse
    {
        $booking = Booking::where('id', $bookingId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $review = BookingReview::where('booking_id', $booking->id)
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json(['review' => $review]);
    }
}
