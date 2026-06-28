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
            $data['owner_parking_slots']    = $court->owner->parking_slots;
            $data['owner_opening_time']     = $court->owner->opening_time;
            $data['owner_closing_time']     = $court->owner->closing_time;
            $data['owner_amenities']        = $court->owner->amenities;
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
        $user = $request->user();
        
        if ($user->role === 'owner') {
            // Owners can only see their own courts
            $courts = Court::with('owner', 'reviews')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($court) => $this->formatCourt($court));
        } else {
            // Regular users can see all courts for discovery
            $courts = Court::with('owner', 'reviews')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($court) => $this->formatCourt($court));
        }

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
            'price_per_hour' => 'required|numeric|min:0',
            'court_image'    => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'about'          => 'nullable|string|max:1000',
            'court_quality'  => 'nullable|in:standard,pro',
            'has_tent'       => 'nullable|boolean',
            'venue_type'     => 'nullable|in:outdoor,indoor',

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagePath = null;
        if ($request->hasFile('court_image')) {
            $file = $request->file('court_image');
            $basename = $request->user()->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')->putFileAs('courts', $file, $basename);
            $imagePath = 'courts/' . $basename;
        }

        $court = Court::create([
            'user_id'         => $request->user()->id,
            'name'            => $request->name,
            'location'        => $request->user()->company_location,
            'court_type'      => $request->court_type ?? 'regular',
            'price_per_hour'  => $request->price_per_hour,
            'court_image'     => $imagePath,
            'about'           => $request->about,
            'court_quality'   => $request->court_quality,
            'has_tent'        => $request->has_tent ?? false,
            'venue_type'      => $request->venue_type,
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
            'price_per_hour' => 'sometimes|numeric|min:0',
            'is_active'      => 'sometimes|boolean',
            'close_reason'   => 'nullable|string|max:500',
            'court_image'    => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'court_type'     => 'nullable|in:regular,open_play',
            'about'          => 'nullable|string|max:1000',
            'court_quality'  => 'nullable|in:standard,pro',
            'has_tent'       => 'nullable|boolean',
            'venue_type'     => 'nullable|in:outdoor,indoor',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('court_image')) {
            if ($court->court_image && Storage::disk('public')->exists($court->court_image)) {
                Storage::disk('public')->delete($court->court_image);
            }
            $file = $request->file('court_image');
            $basename = $request->user()->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')->putFileAs('courts', $file, $basename);
            $court->court_image = 'courts/' . $basename;
        }

        $court->update($request->only(['name', 'court_type', 'price_per_hour', 'is_active', 'close_reason', 'about', 'court_quality', 'has_tent', 'venue_type']));

        return response()->json($this->formatCourt($court));
    }

    public function destroy(Request $request, $id)
    {
        $court = Court::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        if ($court->court_image && Storage::disk('public')->exists($court->court_image)) {
            Storage::disk('public')->delete($court->court_image);
        }

        $court->delete();

        return response()->json(['message' => 'Court deleted successfully']);
    }
}
