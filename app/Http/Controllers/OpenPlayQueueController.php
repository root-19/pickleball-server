<?php

namespace App\Http\Controllers;

use App\Models\OpenPlayQueue;
use App\Models\Court;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OpenPlayQueueController extends Controller
{
    public function joinQueue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'court_id'     => 'required|integer|exists:courts,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'time_slot_start' => 'required|string',
            'time_slot_end'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        \Log::info('Join queue received', [
            'start' => $request->time_slot_start,
            'end' => $request->time_slot_end,
            'start_type' => gettype($request->time_slot_start),
            'end_type' => gettype($request->time_slot_end),
        ]);

        $timePattern = '/^\d{1,2}:\d{2}$/';
        if (!preg_match($timePattern, (string) $request->time_slot_start)) {
            return response()->json(['errors' => ['time_slot_start' => ['Invalid time format. Use HH:MM. Got: ' . $request->time_slot_start]]], 422);
        }
        if (!preg_match($timePattern, (string) $request->time_slot_end)) {
            return response()->json(['errors' => ['time_slot_end' => ['Invalid time format. Use HH:MM. Got: ' . $request->time_slot_end]]], 422);
        }

        $userId = $request->user()->id;

        // Check if user already has an active queue entry
        $existing = OpenPlayQueue::where('user_id', $userId)
            ->whereIn('status', ['waiting', 'matched'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You already have an active queue entry',
                'queue' => $existing->load('user', 'matchedUser', 'court'),
            ], 400);
        }

        // Check how many players are in this slot (waiting + matched)
        $slotPlayers = OpenPlayQueue::where('court_id', $request->court_id)
            ->where('booking_date', $request->booking_date)
            ->where('time_slot_start', $request->time_slot_start)
            ->whereIn('status', ['waiting', 'matched'])
            ->count();

        $slotEndsAt = $this->slotEndsAt($request->booking_date, $request->time_slot_end);

        if (now()->gte($slotEndsAt)) {
            return response()->json(['message' => 'This time slot has already ended.'], 422);
        }

        $queue = OpenPlayQueue::create([
            'user_id' => $userId,
            'court_id' => $request->court_id,
            'booking_date' => $request->booking_date,
            'time_slot_start' => $request->time_slot_start,
            'time_slot_end' => $request->time_slot_end,
            'status' => 'waiting',
            'payment_status' => 'pending',
            'payment_deadline' => $slotEndsAt,
        ]);

        // Check for auto-match (if 2 players are in queue)
        $this->autoMatch($request->court_id, $request->booking_date, $request->time_slot_start);

        $queue->refresh()->load('user:id,name,profile_image', 'matchedUser:id,name,profile_image', 'court');

        if ($queue->status === 'waiting') {
            app(NotificationService::class)->notifyOpenPlayWaiting($queue);
        }

        if ($queue->user->profile_image) {
            $queue->user->profile_image = url('storage/' . $queue->user->profile_image);
        }
        if ($queue->matchedUser?->profile_image) {
            $queue->matchedUser->profile_image = url('storage/' . $queue->matchedUser->profile_image);
        }

        return response()->json(['queue' => $queue], 201);
    }

    public function processPayment(Request $request)
    {
        $userId = $request->user()->id;
        $queue = OpenPlayQueue::where('user_id', $userId)
            ->where('payment_status', 'pending')
            ->whereIn('status', ['waiting', 'matched'])
            ->first();

        if (!$queue) {
            return response()->json(['message' => 'No pending payment found'], 404);
        }

        if ($this->isSlotExpired($queue)) {
            $this->expireQueueIfOverdue($queue);
            return response()->json(['message' => 'This time slot has ended'], 400);
        }

        // Check if this is the first payer (becomes slot_opener)
        $firstPayer = OpenPlayQueue::where('court_id', $queue->court_id)
            ->where('booking_date', $queue->booking_date)
            ->where('time_slot_start', $queue->time_slot_start)
            ->where('payment_status', 'paid')
            ->first();

        $queue->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'slot_opener' => !$firstPayer,
        ]);

        // Re-check for auto-match after payment
        $this->autoMatch($queue->court_id, $queue->booking_date, $queue->time_slot_start);

        $queue->load('user:id,name,profile_image', 'matchedUser:id,name,profile_image', 'court');

        if ($queue->user->profile_image) {
            $queue->user->profile_image = url('storage/' . $queue->user->profile_image);
        }
        if ($queue->matchedUser?->profile_image) {
            $queue->matchedUser->profile_image = url('storage/' . $queue->matchedUser->profile_image);
        }

        return response()->json(['queue' => $queue, 'message' => 'Payment successful']);
    }

    public function getQueueStatus(Request $request)
    {
        $userId = $request->user()->id;
        $queue = OpenPlayQueue::where('user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'completed')
            ->with('user:id,name,profile_image', 'matchedUser:id,name,profile_image', 'court')
            ->first();

        if (!$queue) {
            return response()->json(['queue' => null]);
        }

        $this->expireOverdueSlotQueues(
            $queue->court_id,
            $queue->booking_date,
            $queue->time_slot_start
        );

        $queue->refresh();

        if ($queue->status === 'timeout') {
            return response()->json(['queue' => $queue]);
        }

        if ($queue->user->profile_image) {
            $queue->user->profile_image = url('storage/' . $queue->user->profile_image);
        }
        if ($queue->matchedUser?->profile_image) {
            $queue->matchedUser->profile_image = url('storage/' . $queue->matchedUser->profile_image);
        }

        // Get all waiting players for this court/time slot
        $waitingPlayers = OpenPlayQueue::where('court_id', $queue->court_id)
            ->where('booking_date', $queue->booking_date)
            ->where('time_slot_start', $queue->time_slot_start)
            ->where('status', 'waiting')
            ->with('user:id,name,profile_image')
            ->get();

        $waitingPlayers->each(function ($q) {
            if ($q->user->profile_image) {
                $q->user->profile_image = url('storage/' . $q->user->profile_image);
            }
        });

        return response()->json([
            'queue' => $queue,
            'waiting_players' => $waitingPlayers,
        ]);
    }

    public function cancelQueue(Request $request)
    {
        $userId = $request->user()->id;
        $queue = OpenPlayQueue::where('user_id', $userId)
            ->whereIn('status', ['waiting', 'matched'])
            ->first();

        if (!$queue) {
            return response()->json(['message' => 'No active queue entry found'], 404);
        }

        // Can only cancel if not paid
        if ($queue->payment_status === 'paid') {
            return response()->json(['message' => 'Cannot cancel after payment'], 400);
        }

        $queue->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Queue entry cancelled']);
    }

    public function getCourtQueues(Request $request, $courtId)
    {
        $validator = Validator::make($request->all(), [
            'booking_date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activeQueues = OpenPlayQueue::where('court_id', $courtId)
            ->where('booking_date', $request->booking_date)
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'completed')
            ->get();

        foreach ($activeQueues->groupBy('time_slot_start') as $timeSlotStart => $group) {
            $this->expireOverdueSlotQueues($courtId, $request->booking_date, $timeSlotStart);
        }

        $queues = OpenPlayQueue::where('court_id', $courtId)
            ->where('booking_date', $request->booking_date)
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'completed')
            ->with('user:id,name,profile_image', 'matchedUser:id,name,profile_image')
            ->get()
            ->groupBy('time_slot_start');

        $queues->each(function ($group) {
            $group->each(function ($q) {
                if ($q->user->profile_image) {
                    $q->user->profile_image = url('storage/' . $q->user->profile_image);
                }
                if ($q->matchedUser?->profile_image) {
                    $q->matchedUser->profile_image = url('storage/' . $q->matchedUser->profile_image);
                }
            });
        });

        return response()->json($queues);
    }

    private function autoMatch($courtId, $bookingDate, $timeSlotStart)
    {
        // Count already matched players in this slot
        $alreadyMatched = OpenPlayQueue::where('court_id', $courtId)
            ->where('booking_date', $bookingDate)
            ->where('time_slot_start', $timeSlotStart)
            ->where('status', 'matched')
            ->count();

        // Only match if we have less than 2 matched players
        $slotsAvailable = 2 - $alreadyMatched;
        if ($slotsAvailable <= 0) {
            return; // Slot is full, no more matching
        }

        // Get waiting paid players to potentially match
        $waitingPlayers = OpenPlayQueue::where('court_id', $courtId)
            ->where('booking_date', $bookingDate)
            ->where('time_slot_start', $timeSlotStart)
            ->where('status', 'waiting')
            ->where('payment_status', 'paid')
            ->orderBy('paid_at', 'asc')
            ->limit($slotsAvailable)
            ->get();

        // We need at least 2 players total (already matched + waiting) to create a match
        $totalAvailable = $alreadyMatched + $waitingPlayers->count();
        if ($totalAvailable < 2) {
            return; // Not enough players to form a match
        }

        // Match waiting players with each other or with already matched players
        $now = now();

        // If we have 2+ waiting players, match them together
        if ($waitingPlayers->count() >= 2) {
            $player1 = $waitingPlayers[0];
            $player2 = $waitingPlayers[1];

            $player1->update([
                'status' => 'matched',
                'matched_with' => $player2->user_id,
                'matched_at' => $now,
            ]);

            $player2->update([
                'status' => 'matched',
                'matched_with' => $player1->user_id,
                'matched_at' => $now,
            ]);

            $this->notifyMatchedPlayers($player1, $player2);
        }
        // If we have 1 waiting player and 1 already matched, match them
        elseif ($waitingPlayers->count() === 1 && $alreadyMatched === 1) {
            $waitingPlayer = $waitingPlayers[0];
            $matchedPlayer = OpenPlayQueue::where('court_id', $courtId)
                ->where('booking_date', $bookingDate)
                ->where('time_slot_start', $timeSlotStart)
                ->where('status', 'matched')
                ->first();

            if ($matchedPlayer) {
                $waitingPlayer->update([
                    'status' => 'matched',
                    'matched_with' => $matchedPlayer->user_id,
                    'matched_at' => $now,
                ]);

                // Update the already matched player to point to new match
                $matchedPlayer->update(['matched_with' => $waitingPlayer->user_id]);

                $this->notifyMatchedPlayers($waitingPlayer, $matchedPlayer);
            }
        }
    }

    private function notifyMatchedPlayers(OpenPlayQueue $player1, OpenPlayQueue $player2): void
    {
        $player1 = $player1->fresh(['court', 'user']);
        $player2 = $player2->fresh(['court', 'user']);

        app(NotificationService::class)->notifyOpenPlayMatched($player1, $player2);
    }

    private function slotEndsAt(string|Carbon $bookingDate, string $timeSlotEnd): Carbon
    {
        $date = $bookingDate instanceof Carbon
            ? $bookingDate->toDateString()
            : Carbon::parse($bookingDate)->toDateString();
        $endTime = NotificationService::parseTimeTo24h($timeSlotEnd);

        return Carbon::parse("{$date} {$endTime}");
    }

    private function isSlotExpired(OpenPlayQueue $queue): bool
    {
        return now()->gte($this->slotEndsAt($queue->booking_date, (string) $queue->time_slot_end));
    }

    private function expireQueueIfOverdue(OpenPlayQueue $queue): void
    {
        if (!$this->isSlotExpired($queue) || $queue->status === 'timeout') {
            return;
        }

        if (!in_array($queue->status, ['waiting', 'matched'], true)) {
            return;
        }

        $wasWaitingForMatch = $queue->status === 'waiting'
            || ($queue->status === 'matched' && $queue->payment_status === 'pending');

        $queue->update(['status' => 'timeout']);

        if ($wasWaitingForMatch) {
            $queue->load('court');
            app(NotificationService::class)->notifyOpenPlayTimeout($queue);
        }
    }

    private function expireOverdueSlotQueues(int $courtId, string|Carbon $bookingDate, string $timeSlotStart): void
    {
        $queues = OpenPlayQueue::where('court_id', $courtId)
            ->where('booking_date', $bookingDate)
            ->where('time_slot_start', $timeSlotStart)
            ->whereIn('status', ['waiting', 'matched'])
            ->get();

        foreach ($queues as $queue) {
            $this->expireQueueIfOverdue($queue);
        }
    }
}
