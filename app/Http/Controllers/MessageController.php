<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'booking_id' => 'nullable|integer|exists:bookings,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $currentUserId = $request->user()->id;
        $otherUserId = $request->user_id;

        $query = Message::with(['sender:id,name,profile_image', 'receiver:id,name,profile_image'])
            ->where(function ($q) use ($currentUserId, $otherUserId) {
                $q->where('sender_id', $currentUserId)->where('receiver_id', $otherUserId);
            })
            ->orWhere(function ($q) use ($currentUserId, $otherUserId) {
                $q->where('sender_id', $otherUserId)->where('receiver_id', $currentUserId);
            })
            ->orderBy('created_at', 'asc');

        if ($request->has('booking_id')) {
            $query->where('booking_id', $request->booking_id);
        }

        $messages = $query->get();

        // Mark messages from the other user as read
        Message::where('sender_id', $otherUserId)
            ->where('receiver_id', $currentUserId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|integer|exists:users,id',
            'booking_id' => 'nullable|integer|exists:bookings,id',
            'content' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $message = Message::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $request->receiver_id,
            'booking_id' => $request->booking_id,
            'content' => $request->content,
        ]);

        $message->load(['sender:id,name,profile_image', 'receiver:id,name,profile_image']);

        if ($message->sender->profile_image) {
            $message->sender->profile_image = url('storage/' . $message->sender->profile_image);
        }
        if ($message->receiver->profile_image) {
            $message->receiver->profile_image = url('storage/' . $message->receiver->profile_image);
        }

        return response()->json($message, 201);
    }

    public function conversations(Request $request)
    {
        $currentUserId = $request->user()->id;

        // Get all unique conversations for the current user
        $messages = Message::with(['sender:id,name,profile_image', 'receiver:id,name,profile_image', 'booking:id,court_id'])
            ->where('sender_id', $currentUserId)
            ->orWhere('receiver_id', $currentUserId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($message) use ($currentUserId) {
                $otherUserId = $message->sender_id === $currentUserId
                    ? $message->receiver_id
                    : $message->sender_id;
                return $otherUserId . '_' . ($message->booking_id ?? '0');
            });

        $conversations = $messages->map(function ($group) {
            $latest = $group->first();
            $otherUser = $latest->sender_id === $latest->receiver_id ? $latest->sender : $latest->receiver;

            if ($otherUser->profile_image) {
                $otherUser->profile_image = url('storage/' . $otherUser->profile_image);
            }

            return [
                'other_user' => $otherUser,
                'booking_id' => $latest->booking_id,
                'last_message' => $latest->content,
                'last_message_at' => $latest->created_at,
                'unread_count' => $group->where('receiver_id', $latest->receiver_id)->whereNull('read_at')->count(),
            ];
        })->values();

        return response()->json($conversations);
    }
}
