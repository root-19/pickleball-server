<?php

namespace App\Http\Controllers;

use App\Models\Court;
use Illuminate\Http\Request;

class FavoriteCourtController extends Controller
{
    public function index(Request $request)
    {
        $courts = $request->user()->favoriteCourts()->with('reviews')->get();

        // Add rating data to each court
        $courtsWithRatings = $courts->map(function ($court) {
            $reviews = $court->reviews;
            $reviewCount = $reviews->count();
            $averageRating = $reviewCount > 0 ? $reviews->avg('rating') : 0;
            $court->average_rating = round($averageRating, 1);
            $court->review_count = $reviewCount;
            return $court;
        });

        return response()->json($courtsWithRatings);
    }

    public function store(Request $request, $courtId)
    {
        $court = Court::findOrFail($courtId);
        $user  = $request->user();

        if ($user->favoriteCourts()->where('court_id', $courtId)->exists()) {
            return response()->json(['message' => 'Already in favorites'], 409);
        }

        $user->favoriteCourts()->attach($courtId);
        return response()->json(['message' => 'Added to favorites'], 201);
    }

    public function destroy(Request $request, $courtId)
    {
        $request->user()->favoriteCourts()->detach($courtId);
        return response()->json(['message' => 'Removed from favorites']);
    }

    public function check(Request $request, $courtId)
    {
        $isFavorite = $request->user()->favoriteCourts()->where('court_id', $courtId)->exists();
        return response()->json(['is_favorite' => $isFavorite]);
    }
}
