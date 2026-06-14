<?php

namespace App\Http\Controllers;

use App\Models\Court;
use Illuminate\Http\Request;

class FavoriteCourtController extends Controller
{
    public function index(Request $request)
    {
        $courts = $request->user()->favoriteCourts()->get();
        return response()->json($courts);
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
