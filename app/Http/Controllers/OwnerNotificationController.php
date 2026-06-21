<?php

namespace App\Http\Controllers;

use App\Models\OwnerNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = OwnerNotification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = OwnerNotification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['notification' => $notification->fresh()]);
    }
}
