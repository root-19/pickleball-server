<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Booking;
use App\Models\BookingReview;

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
            'company_name' => 'nullable|string|max:255',
            'company_location' => 'nullable|string|max:500',
            'parking_slots' => 'nullable|string|max:10',
            'opening_time' => 'nullable|string|max:20',
            'closing_time' => 'nullable|string|max:20',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $amenitiesData = $request->has('amenities') ? json_encode($request->amenities) : null;

        $user->update($request->only(['name', 'email', 'phone', 'bio', 'company_name', 'company_location', 'parking_slots', 'opening_time', 'closing_time']) + ['amenities' => $amenitiesData]);

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
        if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
            Storage::disk('public')->delete($user->profile_image);
        }

        $file = $request->file('profile_image');
        $basename = $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        Storage::disk('public')->putFileAs('profiles', $file, $basename);

        $user->update(['profile_image' => 'profiles/' . $basename]);

        return response()->json($this->formatUser($user));
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        $totalBookings = Booking::where('user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->count();

        $wins = BookingReview::where('user_id', $userId)
            ->where('result', 'win')
            ->count();
        $losses = BookingReview::where('user_id', $userId)
            ->where('result', 'lose')
            ->count();
        $reviewCount = BookingReview::where('user_id', $userId)->count();

        return response()->json([
            'total_bookings' => $totalBookings,
            'wins' => $wins,
            'losses' => $losses,
            'review_count' => $reviewCount,
        ]);
    }
}
