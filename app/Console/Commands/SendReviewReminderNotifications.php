<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingReview;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendReviewReminderNotifications extends Command
{
    protected $signature = 'notifications:send-review-reminders';
    protected $description = 'Send review request notifications 20 minutes after booking end time';

    public function handle(NotificationService $notificationService): int
    {
        $bookings = Booking::with('court')
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereNull('review_reminder_sent_at')
            ->get();

        $sent = 0;

        foreach ($bookings as $booking) {
            $dueAt = NotificationService::reviewReminderDueAt($booking);

            if (now()->lt($dueAt)) {
                continue;
            }

            $hasReview = BookingReview::where('booking_id', $booking->id)
                ->where('user_id', $booking->user_id)
                ->exists();

            if ($hasReview) {
                $booking->update(['review_reminder_sent_at' => now()]);
                continue;
            }

            $alreadySent = Notification::where('user_id', $booking->user_id)
                ->where('type', 'review_request')
                ->where('data->booking_id', $booking->id)
                ->exists();

            if ($alreadySent) {
                $booking->update(['review_reminder_sent_at' => now()]);
                continue;
            }

            $notificationService->notifyReviewRequest($booking);
            $booking->update(['review_reminder_sent_at' => now()]);
            $sent++;
        }

        $this->info("Sent {$sent} review reminder notification(s).");

        return self::SUCCESS;
    }
}
