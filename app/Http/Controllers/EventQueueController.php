<?php

namespace App\Http\Controllers;

use App\Models\EventQueue;
use App\Models\PickleEvent;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EventQueueController extends Controller
{
    public function join(Request $request)
    {
        $request->validate([
            'event_id' => 'required|integer|exists:pickle_events,id',
        ]);

        $event = PickleEvent::findOrFail($request->event_id);

        if (!$event->is_active) {
            return response()->json(['message' => 'This event is no longer active.'], 422);
        }

        $userId = $request->user()->id;

        // Check any existing entry (any status)
        $existing = EventQueue::where('user_id', $userId)
            ->where('event_id', $event->id)
            ->first();

        if ($existing) {
            // Allow re-join only if previously cancelled
            if ($existing->status === 'cancelled') {
                $existing->update(['status' => 'waiting', 'payment_status' => 'pending', 'paid_at' => null]);
                $existing->load('user:id,name,profile_image', 'event');
                $this->formatQueueImages($existing);
                return response()->json(['queue' => $existing], 201);
            }
            return response()->json([
                'message' => 'You already joined this event.',
                'queue'   => $existing->load('user:id,name,profile_image', 'event'),
            ], 400);
        }

        // Count current active joiners
        $joined = EventQueue::where('event_id', $event->id)
            ->whereIn('status', ['waiting', 'confirmed'])
            ->count();

        if ($joined >= $event->max_players) {
            return response()->json(['message' => 'This event is already full.'], 422);
        }

        try {
            $queue = EventQueue::create([
                'user_id'  => $userId,
                'event_id' => $event->id,
                'status'   => 'waiting',
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $existing = EventQueue::where('user_id', $userId)->where('event_id', $event->id)->first();
            return response()->json([
                'message' => 'You already joined this event.',
                'queue'   => $existing?->load('user:id,name,profile_image', 'event'),
            ], 400);
        }

        $queue->load('user:id,name,profile_image', 'event');
        $this->formatQueueImages($queue);

        // Notify if event just became full — all waiting players can now pay
        $newJoined = $joined + 1;
        if ($newJoined >= $event->max_players) {
            $this->notifyEventFull($event);
        } else {
            app(NotificationService::class)->create(
                $userId,
                'event_joined',
                'You joined an event!',
                "You are on the waiting list for \"{$event->title}\". You will be notified once the event is full and payment opens.",
                ['event_id' => $event->id, 'event_title' => $event->title]
            );
        }

        return response()->json(['queue' => $queue], 201);
    }

    public function pay(Request $request)
    {
        $request->validate([
            'event_id' => 'required|integer|exists:pickle_events,id',
        ]);

        $userId  = $request->user()->id;
        $eventId = $request->event_id;

        $queue = EventQueue::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->where('status', 'waiting')
            ->where('payment_status', 'pending')
            ->first();

        if (!$queue) {
            return response()->json(['message' => 'No pending payment found for this event.'], 404);
        }

        $event = PickleEvent::findOrFail($eventId);

        // Count already paid confirmed slots — must be < max_players
        $paidCount = EventQueue::where('event_id', $eventId)
            ->where('payment_status', 'paid')
            ->where('status', 'confirmed')
            ->count();

        if ($paidCount >= $event->max_players) {
            $queue->update(['status' => 'timeout']);
            return response()->json(['message' => 'All slots have been filled by other players.'], 422);
        }

        // First-pay-wins: atomic lock so only one payment processes at a time
        DB::transaction(function () use ($queue, $event) {
            $paidNow = EventQueue::where('event_id', $event->id)
                ->where('payment_status', 'paid')
                ->where('status', 'confirmed')
                ->lockForUpdate()
                ->count();

            if ($paidNow >= $event->max_players) {
                $queue->update(['status' => 'timeout']);
                return;
            }

            $isFirst = $paidNow === 0;

            $queue->update([
                'status'         => 'confirmed',
                'payment_status' => 'paid',
                'paid_at'        => now(),
                'first_payer'    => $isFirst,
            ]);

            app(NotificationService::class)->create(
                $queue->user_id,
                'event_paid',
                'Slot Confirmed!',
                "Your slot for \"{$event->title}\" is confirmed. See you on " . $event->event_date . "!",
                ['event_id' => $event->id, 'event_title' => $event->title]
            );

            // If all slots now filled, close the event
            $totalPaid = EventQueue::where('event_id', $event->id)
                ->where('payment_status', 'paid')
                ->where('status', 'confirmed')
                ->count();

            if ($totalPaid >= $event->max_players) {
                $event->update(['is_active' => false]);

                // Timeout all remaining waiting players
                EventQueue::where('event_id', $event->id)
                    ->where('status', 'waiting')
                    ->update(['status' => 'timeout']);
            }
        });

        $queue->refresh()->load('user:id,name,profile_image', 'event');
        $this->formatQueueImages($queue);

        return response()->json(['queue' => $queue, 'message' => 'Payment successful. Slot confirmed!']);
    }

    public function status(Request $request)
    {
        $request->validate([
            'event_id' => 'required|integer|exists:pickle_events,id',
        ]);

        $userId = $request->user()->id;

        $queue = EventQueue::where('user_id', $userId)
            ->where('event_id', $request->event_id)
            ->with('user:id,name,profile_image', 'event')
            ->first();

        $event = PickleEvent::findOrFail($request->event_id);
        $joinedCount = EventQueue::where('event_id', $request->event_id)
            ->whereIn('status', ['waiting', 'confirmed'])
            ->count();
        $paidCount = EventQueue::where('event_id', $request->event_id)
            ->where('status', 'confirmed')
            ->where('payment_status', 'paid')
            ->count();

        if ($queue) $this->formatQueueImages($queue);

        return response()->json([
            'queue'        => $queue,
            'joined_count' => $joinedCount,
            'paid_count'   => $paidCount,
            'max_players'  => $event->max_players,
            'is_full'      => $joinedCount >= $event->max_players,
            'all_paid'     => $paidCount >= $event->max_players,
        ]);
    }

    public function cancel(Request $request)
    {
        $request->validate([
            'event_id' => 'required|integer|exists:pickle_events,id',
        ]);

        $queue = EventQueue::where('user_id', $request->user()->id)
            ->where('event_id', $request->event_id)
            ->where('status', 'waiting')
            ->where('payment_status', 'pending')
            ->first();

        if (!$queue) {
            return response()->json(['message' => 'No cancellable queue entry found.'], 404);
        }

        $queue->update(['status' => 'cancelled']);

        return response()->json(['message' => 'You have left the event.']);
    }

    public function players(Request $request, $eventId)
    {
        $queues = EventQueue::where('event_id', $eventId)
            ->whereIn('status', ['waiting', 'confirmed'])
            ->with('user:id,name,profile_image')
            ->orderBy('joined_at')
            ->get();

        $queues->each(fn($q) => $this->formatQueueImages($q));

        return response()->json($queues);
    }

    public function createSource(Request $request)
    {
        $request->validate([
            'event_id'       => 'required|integer|exists:pickle_events,id',
            'payment_method' => 'required|in:gcash,maya',
        ]);

        $userId  = $request->user()->id;
        $event   = PickleEvent::findOrFail($request->event_id);

        $queue = EventQueue::where('user_id', $userId)
            ->where('event_id', $event->id)
            ->where('status', 'waiting')
            ->where('payment_status', 'pending')
            ->first();

        if (!$queue) {
            return response()->json(['message' => 'No eligible queue entry found.'], 404);
        }

        $amountCents = (int) round($event->price_per_head * 100);
        $ref         = 'EV-' . $event->id . '-' . strtoupper(Str::random(6));
        $secretKey   = config('services.paymongo.secret_key');
        $baseUrl     = 'https://api.paymongo.com/v1';

        $response = Http::withBasicAuth($secretKey, '')
            ->post("{$baseUrl}/sources", [
                'data' => [
                    'attributes' => [
                        'amount'   => $amountCents,
                        'currency' => 'PHP',
                        'type'     => $request->payment_method,
                        'redirect' => [
                            'success' => url('/api/payments/callback/success?ref=' . $ref),
                            'failed'  => url('/api/payments/callback/failed?ref=' . $ref),
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
            return response()->json(['message' => 'Failed to create payment source.', 'detail' => $response->json()], 500);
        }

        $source = $response->json('data');

        $queue->update([
            'payment_method'     => $request->payment_method,
            'paymongo_source_id' => $source['id'],
        ]);

        return response()->json([
            'source_id'    => $source['id'],
            'checkout_url' => $source['attributes']['redirect']['checkout_url'],
            'queue_id'     => $queue->id,
            'amount'       => $event->price_per_head,
        ]);
    }

    public function verifySource(Request $request)
    {
        $request->validate([
            'event_id'  => 'required|integer|exists:pickle_events,id',
            'source_id' => 'required|string',
        ]);

        $userId = $request->user()->id;
        $event  = PickleEvent::findOrFail($request->event_id);

        $queue = EventQueue::where('user_id', $userId)
            ->where('event_id', $event->id)
            ->where('paymongo_source_id', $request->source_id)
            ->first();

        if (!$queue) {
            return response()->json(['message' => 'Queue entry not found.'], 404);
        }

        if ($queue->payment_status === 'paid') {
            return response()->json(['status' => 'paid', 'queue' => $queue]);
        }

        $secretKey = config('services.paymongo.secret_key');
        $baseUrl   = 'https://api.paymongo.com/v1';

        $srcRes = Http::withBasicAuth($secretKey, '')->get("{$baseUrl}/sources/{$request->source_id}");
        if (!$srcRes->successful()) {
            return response()->json(['message' => 'Could not verify source.'], 500);
        }

        $sourceStatus = $srcRes->json('data.attributes.status');

        if ($sourceStatus === 'chargeable') {
            $chargeRes = Http::withBasicAuth($secretKey, '')
                ->post("{$baseUrl}/payments", [
                    'data' => [
                        'attributes' => [
                            'amount'      => (int) round($event->price_per_head * 100),
                            'currency'    => 'PHP',
                            'source'      => ['id' => $request->source_id, 'type' => 'source'],
                            'description' => "Event Slot - {$event->title}",
                        ],
                    ],
                ]);

            if ($chargeRes->successful() && $chargeRes->json('data.attributes.status') === 'paid') {
                $paymentId = $chargeRes->json('data.id');
                return $this->confirmQueuePayment($queue, $event, $paymentId);
            }
        }

        return response()->json(['status' => $sourceStatus, 'message' => 'Payment not yet completed.']);
    }

    public function createQrPh(Request $request)
    {
        $request->validate([
            'event_id' => 'required|integer|exists:pickle_events,id',
        ]);

        $userId  = $request->user()->id;
        $event   = PickleEvent::findOrFail($request->event_id);

        $queue = EventQueue::where('user_id', $userId)
            ->where('event_id', $event->id)
            ->where('status', 'waiting')
            ->where('payment_status', 'pending')
            ->first();

        if (!$queue) {
            return response()->json(['message' => 'No eligible queue entry found.'], 404);
        }

        $amountCents = (int) round($event->price_per_head * 100);
        $secretKey   = config('services.paymongo.secret_key');
        $publicKey   = config('services.paymongo.public_key');
        $baseUrl     = 'https://api.paymongo.com/v1';

        $intentRes = Http::withBasicAuth($secretKey, '')
            ->post("{$baseUrl}/payment_intents", [
                'data' => [
                    'attributes' => [
                        'amount'                 => $amountCents,
                        'currency'               => 'PHP',
                        'payment_method_allowed' => ['qrph'],
                        'description'            => "Event Slot - {$event->title}",
                    ],
                ],
            ]);

        if (!$intentRes->successful()) {
            return response()->json(['message' => 'Failed to create payment intent.'], 500);
        }

        $intentId  = $intentRes->json('data.id');
        $clientKey = $intentRes->json('data.attributes.client_key');

        $pmRes = Http::withBasicAuth($publicKey, '')
            ->post("{$baseUrl}/payment_methods", [
                'data' => ['attributes' => ['type' => 'qrph']],
            ]);

        if (!$pmRes->successful()) {
            return response()->json(['message' => 'Failed to create QR payment method.'], 500);
        }

        $pmId = $pmRes->json('data.id');

        $attachRes = Http::withBasicAuth($publicKey, '')
            ->post("{$baseUrl}/payment_intents/{$intentId}/attach", [
                'data' => [
                    'attributes' => [
                        'payment_method' => $pmId,
                        'client_key'     => $clientKey,
                    ],
                ],
            ]);

        if (!$attachRes->successful()) {
            return response()->json(['message' => 'Failed to attach QR payment method.'], 500);
        }

        $qrImageUrl = $attachRes->json('data.attributes.next_action.code.image_url');
        if (!$qrImageUrl) {
            return response()->json(['message' => 'QR code not returned.'], 500);
        }

        $queue->update([
            'payment_method'     => 'qrph',
            'paymongo_source_id' => $intentId,
        ]);

        return response()->json([
            'qr_image_url' => $qrImageUrl,
            'intent_id'    => $intentId,
            'client_key'   => $clientKey,
            'queue_id'     => $queue->id,
            'amount'       => $event->price_per_head,
            'expires_in'   => 1800,
        ]);
    }

    public function verifyIntent(Request $request)
    {
        $request->validate([
            'event_id'   => 'required|integer|exists:pickle_events,id',
            'intent_id'  => 'required|string',
            'client_key' => 'required|string',
        ]);

        $userId  = $request->user()->id;
        $event   = PickleEvent::findOrFail($request->event_id);

        $queue = EventQueue::where('user_id', $userId)
            ->where('event_id', $event->id)
            ->where('paymongo_source_id', $request->intent_id)
            ->first();

        if (!$queue) {
            return response()->json(['message' => 'Queue entry not found.'], 404);
        }

        if ($queue->payment_status === 'paid') {
            return response()->json(['status' => 'succeeded', 'queue' => $queue]);
        }

        $publicKey = config('services.paymongo.public_key');
        $baseUrl   = 'https://api.paymongo.com/v1';

        $res = Http::withBasicAuth($publicKey, '')
            ->get("{$baseUrl}/payment_intents/{$request->intent_id}?client_key={$request->client_key}");

        if (!$res->successful()) {
            return response()->json(['message' => 'Could not verify payment intent.'], 500);
        }

        $intentStatus = $res->json('data.attributes.status');

        if ($intentStatus === 'succeeded') {
            $paymentId = $res->json('data.attributes.payments.0.id');
            return $this->confirmQueuePayment($queue, $event, $paymentId);
        }

        return response()->json(['status' => $intentStatus]);
    }

    private function confirmQueuePayment(EventQueue $queue, PickleEvent $event, ?string $paymentId): \Illuminate\Http\JsonResponse
    {
        DB::transaction(function () use ($queue, $event, $paymentId) {
            $paidNow = EventQueue::where('event_id', $event->id)
                ->where('payment_status', 'paid')
                ->where('status', 'confirmed')
                ->lockForUpdate()
                ->count();

            if ($paidNow >= $event->max_players) {
                $queue->update(['status' => 'timeout']);
                return;
            }

            $queue->update([
                'status'              => 'confirmed',
                'payment_status'      => 'paid',
                'paid_at'             => now(),
                'first_payer'         => $paidNow === 0,
                'paymongo_payment_id' => $paymentId,
            ]);

            app(NotificationService::class)->create(
                $queue->user_id,
                'event_paid',
                'Slot Confirmed!',
                "Your slot for \"{$event->title}\" is confirmed. See you on {$event->event_date}!",
                ['event_id' => $event->id, 'event_title' => $event->title]
            );

            $totalPaid = EventQueue::where('event_id', $event->id)
                ->where('payment_status', 'paid')
                ->where('status', 'confirmed')
                ->count();

            if ($totalPaid >= $event->max_players) {
                $event->update(['is_active' => false]);
                EventQueue::where('event_id', $event->id)
                    ->where('status', 'waiting')
                    ->update(['status' => 'timeout']);
            }
        });

        $queue->refresh();

        if ($queue->status === 'timeout') {
            return response()->json([
                'status'  => 'conflict',
                'message' => 'All slots were filled by other players. Your payment will need to be refunded.',
            ], 409);
        }

        return response()->json(['status' => 'succeeded', 'queue' => $queue]);
    }

    private function notifyEventFull(PickleEvent $event): void
    {
        $waitingPlayers = EventQueue::where('event_id', $event->id)
            ->where('status', 'waiting')
            ->where('payment_status', 'pending')
            ->get();

        foreach ($waitingPlayers as $q) {
            app(NotificationService::class)->create(
                $q->user_id,
                'event_full',
                'Event is Full — Pay Now!',
                "The event \"{$event->title}\" is now full! Pay ₱{$event->price_per_head} to confirm your slot. First-come, first-served!",
                ['event_id' => $event->id, 'event_title' => $event->title, 'price_per_head' => $event->price_per_head]
            );
        }
    }

    private function formatQueueImages(EventQueue $queue): void
    {
        if ($queue->user?->profile_image && !str_starts_with($queue->user->profile_image, 'http')) {
            $queue->user->profile_image = url('storage/' . $queue->user->profile_image);
        }
    }
}
