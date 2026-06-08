<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Format user data with full image URL.
     */
    private function formatUser($user)
    {
        $data = $user->toArray();
        if ($user->profile_image) {
            $data['profile_image'] = url('storage/' . $user->profile_image);
        }
        return $data;
    }

    /**
     * Get the authenticated user's profile.
     */
    public function show(Request $request)
    {
        return response()->json($this->formatUser($request->user()));
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only(['name', 'email', 'phone', 'bio']));

        return response()->json($this->formatUser($user));
    }

    /**
     * Upload profile image.
     */
    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'profile_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Delete old image if exists
        if ($user->profile_image && Storage::exists('public/' . $user->profile_image)) {
            Storage::delete('public/' . $user->profile_image);
        }

        $file = $request->file('profile_image');
        $filename = 'profiles/' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('public', $filename);

        $user->update(['profile_image' => $filename]);

        return response()->json($this->formatUser($user));
    }
}
