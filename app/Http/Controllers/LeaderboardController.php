<?php

namespace App\Http\Controllers;

use App\Models\BookingReview;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();

        $quarterStart = $now->copy()->firstOfQuarter()->startOfDay();
        $quarterEnd = $now->copy()->lastOfQuarter()->endOfDay();

        $yearStart = $now->copy()->startOfYear()->startOfDay();
        $yearEnd = $now->copy()->endOfYear()->endOfDay();

        return response()->json([
            'quarterly' => $this->buildLeaderboard($quarterStart, $quarterEnd, 'Q' . $quarterStart->quarter . ' ' . $quarterStart->year),
            'yearly' => $this->buildLeaderboard($yearStart, $yearEnd, 'Year ' . $yearStart->year),
        ]);
    }

    private function buildLeaderboard(Carbon $start, Carbon $end, string $label): array
    {
        $reviews = BookingReview::with('user:id,name,profile_image')
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->groupBy('user_id')
            ->map(function ($items) {
                $user = $items->first()->user;
                if (!$user) {
                    return null;
                }

                $wins = $items->where('result', 'win')->count();
                if ($wins === 0) {
                    return null;
                }

                $games = $items->count();

                return [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'wins' => $wins,
                    'games' => $games,
                    'profile_image' => $user->profile_image
                        ? url('storage/' . $user->profile_image)
                        : null,
                ];
            })
            ->filter();

        $entries = array_values($reviews->toArray());

        usort($entries, function ($a, $b) {
            if ($a['wins'] === $b['wins']) {
                return $b['games'] <=> $a['games'];
            }

            return $b['wins'] <=> $a['wins'];
        });

        $topTen = array_slice($entries, 0, 10);
        $topThree = array_slice($entries, 0, 3);

        return [
            'label' => $label,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'top_three' => $topThree,
            'top_ten' => $topTen,
        ];
    }
}
