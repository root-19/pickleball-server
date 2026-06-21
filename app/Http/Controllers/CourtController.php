<?php

namespace App\Http\Controllers;

use App\Models\Court;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CourtController extends Controller
{
    private function formatCourt(Court $court)
    {
        $data = $court->toArray();
        if ($court->court_image) {
            $data['court_image'] = url('storage/' . $court->court_image);
        }
        if ($court->relationLoaded('owner') && $court->owner) {
            $data['owner_name']             = $court->owner->name;
            $data['owner_company']          = $court->owner->company_name;
            $data['owner_company_location'] = $court->owner->company_location;
            $data['owner_profile_image']    = $court->owner->profile_image
                ? url('storage/' . $court->owner->profile_image)
                : null;
        }

        // Calculate real-time rating data
        $reviews = $court->reviews;
        $reviewCount = $reviews->count();
        $averageRating = $reviewCount > 0 ? $reviews->avg('rating') : 0;
        $data['average_rating'] = round($averageRating, 1);
        $data['review_count'] = $reviewCount;

        return $data;
    }

    public function browse(Request $request)
    {
        $courts = Court::with('owner', 'reviews')
            ->get()
            ->map(fn($court) => $this->formatCourt($court));

        return response()->json($courts);
    }

    public function index(Request $request)
    {
        $courts = Court::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($court) => $this->formatCourt($court));

        return response()->json($courts);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'location'       => 'required|string|max:500',
            'price_per_hour' => 'required|numeric|min:0',
            'time_slots'     => 'required|array|min:1',
            'time_slots.*.start' => 'required|string',
            'time_slots.*.end'   => 'required|string',
            'court_image'    => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'latitude'       => 'nullable|numeric|between:-90,90',
            'longitude'      => 'nullable|numeric|between:-180,180',
            'court_type'     => 'nullable|in:regular,open_play',
            'amenities'      => 'nullable|array',
            'amenities.*'    => 'string',
            'about'          => 'nullable|string|max:1000',
            'court_quality'  => 'nullable|in:standard,pro',
            'has_tent'       => 'nullable|boolean',
            'venue_type'     => 'nullable|in:outdoor,indoor',
            'parking_slots'  => 'nullable|integer|min:0|max:9999',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagePath = null;
        if ($request->hasFile('court_image')) {
            $file = $request->file('court_image');
            $filename = 'courts/' . $request->user()->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public', $filename);
            $imagePath = $filename;
        }

        $amenities = $request->has('amenities') ? $request->amenities : [];

        $court = Court::create([
            'user_id'         => $request->user()->id,
            'name'            => $request->name,
            'court_type'      => $request->court_type ?? 'regular',
            'location'        => $request->location,
            'price_per_hour'  => $request->price_per_hour,
            'time_slots'      => $request->time_slots,
            'court_image'     => $imagePath,
            'latitude'        => $request->latitude,
            'longitude'       => $request->longitude,
            'amenities'       => $amenities,
            'about'           => $request->about,
            'court_quality'   => $request->court_quality,
            'has_tent'        => $request->has_tent ?? false,
            'venue_type'      => $request->venue_type,
            'parking_slots'   => $request->parking_slots,
        ]);

        return response()->json($this->formatCourt($court), 201);
    }

    public function show(Request $request, $id)
    {
        $court = Court::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        return response()->json($this->formatCourt($court));
    }

    public function update(Request $request, $id)
    {
        $court = Court::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name'           => 'sometimes|string|max:255',
            'location'       => 'sometimes|string|max:500',
            'price_per_hour' => 'sometimes|numeric|min:0',
            'time_slots'     => 'sometimes|array|min:1',
            'time_slots.*.start' => 'required_with:time_slots|string',
            'time_slots.*.end'   => 'required_with:time_slots|string',
            'is_active'      => 'sometimes|boolean',
            'close_reason'   => 'nullable|string|max:500',
            'court_image'    => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'court_type'     => 'nullable|in:regular,open_play',
            'amenities'      => 'nullable|array',
            'amenities.*'    => 'string',
            'about'          => 'nullable|string|max:1000',
            'court_quality'  => 'nullable|in:standard,pro',
            'has_tent'       => 'nullable|boolean',
            'venue_type'     => 'nullable|in:outdoor,indoor',
            'parking_slots'  => 'nullable|integer|min:0|max:9999',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('court_image')) {
            if ($court->court_image && Storage::exists('public/' . $court->court_image)) {
                Storage::delete('public/' . $court->court_image);
            }
            $file = $request->file('court_image');
            $filename = 'courts/' . $request->user()->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public', $filename);
            $court->court_image = $filename;
        }

        $court->update($request->only(['name', 'court_type', 'location', 'price_per_hour', 'time_slots', 'is_active', 'close_reason', 'amenities', 'about', 'court_quality', 'has_tent', 'venue_type', 'parking_slots']));

        return response()->json($this->formatCourt($court));
    }

    public function destroy(Request $request, $id)
    {
        $court = Court::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        if ($court->court_image && Storage::exists('public/' . $court->court_image)) {
            Storage::delete('public/' . $court->court_image);
        }

        $court->delete();

        return response()->json(['message' => 'Court deleted successfully']);
    }
}
