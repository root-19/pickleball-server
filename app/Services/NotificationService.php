<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\OwnerNotification;
use App\Models\OpenPlayQueue;
use Carbon\Carbon;

class NotificationService
{
    public function create(int $userId, string $type, string $title, string $body, ?array $data = null): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    public function notifyBookingConfirmed(Booking $booking): Notification
    {
        $courtName = $booking->court->name ?? 'Court';

        return $this->create(
            $booking->user_id,
            'booking',
            'Booking Confirmed',
            "Your booking at {$courtName} on {$booking->booking_date} ({$booking->time_slot_start} – {$booking->time_slot_end}) is confirmed.",
            [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'court_id' => $booking->court_id,
                'court_name' => $courtName,
                'duration_hours' => $booking->duration_hours,
            ]
        );
    }

    public function notifyOpenPlayWaiting(OpenPlayQueue $queue): Notification
    {
        $courtName = $queue->court->name ?? 'Court';

        return $this->create(
            $queue->user_id,
            'open_play_waiting',
            'Waiting for Match',
            "You joined the waiting list at {$courtName} ({$queue->time_slot_start} – {$queue->time_slot_end}). A second player is needed before you can proceed.",
            [
                'open_play_queue_id' => $queue->id,
                'court_id' => $queue->court_id,
                'court_name' => $courtName,
            ]
        );
    }

    public function notifyOpenPlayMatched(OpenPlayQueue $queue, OpenPlayQueue $opponent): void
    {
        $courtName = $queue->court->name ?? 'Court';

        foreach ([$queue, $opponent] as $entry) {
            $other = $entry->id === $queue->id ? $opponent : $queue;

            $this->create(
                $entry->user_id,
                'open_play_matched',
                'Matched!',
                "You've been matched with {$other->user->name} at {$courtName}. You can now pay to confirm your spot.",
                [
                    'open_play_queue_id' => $entry->id,
                    'court_id' => $entry->court_id,
                    'court_name' => $courtName,
                ]
            );
        }
    }

    public function notifyOpenPlayTimeout(OpenPlayQueue $queue): Notification
    {
        $courtName = $queue->court->name ?? 'Court';

        return $this->create(
            $queue->user_id,
            'open_play_timeout',
            'Open Play Slot Closed',
            "Your open play slot at {$courtName} closed without a match. No opponent joined in time.",
            [
                'open_play_queue_id' => $queue->id,
                'court_id' => $queue->court_id,
                'court_name' => $courtName,
            ]
        );
    }

    public function notifyReviewRequest(Booking $booking): Notification
    {
        $courtName = $booking->court->name ?? 'Court';

        return $this->create(
            $booking->user_id,
            'review_request',
            'How was your game?',
            "Your session at {$courtName} has ended. Rate your experience and tell us — did you win or lose?",
            [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'court_id' => $booking->court_id,
                'court_name' => $courtName,
                'duration_hours' => $booking->duration_hours,
            ]
        );
    }

    /**
     * Parse booking end datetime from date + time_slot_end, then add 20 minutes.
     */
    public static function reviewReminderDueAt(Booking $booking): Carbon
    {
        $endTime = self::parseTimeTo24h($booking->time_slot_end);
        $date = $booking->booking_date instanceof Carbon
            ? $booking->booking_date->toDateString()
            : Carbon::parse($booking->booking_date)->toDateString();
        $endAt = Carbon::parse("{$date} {$endTime}");

        return $endAt->addMinutes(20);
    }

    public static function parseTimeTo24h(string $time): string
    {
        $time = trim($time);

        if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $time, $m)) {
            $h = (int) $m[1];
            $min = $m[2];
            $ampm = strtoupper($m[3]);
            if ($ampm === 'PM' && $h !== 12) $h += 12;
            if ($ampm === 'AM' && $h === 12) $h = 0;
            return sprintf('%02d:%s:00', $h, $min);
        }

        $parts = explode(':', $time);
        $h = str_pad($parts[0] ?? '00', 2, '0', STR_PAD_LEFT);
        $min = str_pad($parts[1] ?? '00', 2, '0', STR_PAD_LEFT);

        return "{$h}:{$min}:00";
    }

    public function createOwnerNotification(int $userId, string $type, string $title, string $body, ?array $data = null): OwnerNotification
    {
        return OwnerNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    public function notifyOwnerBookingConfirmed(Booking $booking): OwnerNotification
    {
        $courtName = $booking->court->name ?? 'Court';
        $ownerId = $booking->court->user_id;

        return $this->createOwnerNotification(
            $ownerId,
            'booking',
            'New Booking Received',
            "{$booking->user->name} booked {$courtName} on {$booking->booking_date} ({$booking->time_slot_start} – {$booking->time_slot_end}).",
            [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'court_id' => $booking->court_id,
                'court_name' => $courtName,
                'player_name' => $booking->user->name,
                'total_price' => $booking->total_price,
            ]
        );
    }

    public function notifyOwnerReviewAdded(Booking $booking, int $rating): OwnerNotification
    {
        $courtName = $booking->court->name ?? 'Court';
        $ownerId = $booking->court->user_id;

        return $this->createOwnerNotification(
            $ownerId,
            'review',
            'New Review Received',
            "{$booking->user->name} left a {$rating}-star review for {$courtName}.",
            [
                'booking_id' => $booking->id,
                'court_id' => $booking->court_id,
                'court_name' => $courtName,
                'player_name' => $booking->user->name,
                'rating' => $rating,
            ]
        );
    }
}
