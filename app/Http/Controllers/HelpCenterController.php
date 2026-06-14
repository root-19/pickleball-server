<?php

namespace App\Http\Controllers;

use App\Models\HelpCenterMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HelpCenterController extends Controller
{
    public function index(Request $request)
    {
        $messages = HelpCenterMessage::with(['user:id,name,profile_image', 'admin:id,name,profile_image'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                if ($message->user && $message->user->profile_image) {
                    $message->user->profile_image = url('storage/' . $message->user->profile_image);
                }

                if ($message->admin && $message->admin->profile_image) {
                    $message->admin->profile_image = url('storage/' . $message->admin->profile_image);
                }

                return $message;
            });

        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $helpMessage = HelpCenterMessage::create([
            'user_id' => $request->user()->id,
            'message' => $request->message,
        ]);

        $helpMessage->load(['user:id,name,profile_image', 'admin:id,name,profile_image']);

        if ($helpMessage->user && $helpMessage->user->profile_image) {
            $helpMessage->user->profile_image = url('storage/' . $helpMessage->user->profile_image);
        }

        return response()->json($helpMessage, 201);
    }
}
