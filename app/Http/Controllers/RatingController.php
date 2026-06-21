<?php

namespace App\Http\Controllers;

use App\Http\Resources\RatingResource;
use App\Models\Contest;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class RatingController extends Controller
{
    public function index()
    {
        $users = User::where('user_type', '=', 'user')->with('rating')->get();

        $usersArray = $users->map(function ($user) {
            $participationCount = $user->rating ? count(json_decode($user->rating->contest_ratings)) : 0;
            $currentRating = $user->rating ? $user->rating->current_rating : 0;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'participationCount' => $participationCount,
                'currentRating' => $currentRating,
            ];
        })->toArray();

        usort($usersArray, function ($a, $b) {
            return $b['currentRating'] <=> $a['currentRating'];
        });
        $users = $users->sortByDesc('currentRating')->values();

        $perPage = 50;
        $page = request()->get('page', 1);
        $paginatedRatings = new LengthAwarePaginator(
            $users->forPage($page, $perPage),
            $users->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return RatingResource::collection($paginatedRatings);
    }

    public function store(Contest $contest)
    {
        $standings = $contest->standings;
        $participants = json_decode($standings->result);
        $newRatings = $this->calculateContestRatings($participants);

        $usernames = array_map(fn($p) => $p->username, $participants);
        $users = User::query()->whereIn('name', $usernames)->with('rating')->get()->keyBy('name');

        foreach ($participants as $index => $participant) {
            $user = $users->get($participant->username);

            if (!$user) {
                continue;
            }

            $currentRating = $user->rating ? $user->rating->current_rating : 0;
            $newRating = $newRatings[$index];
            $contestRating = $newRating - $currentRating;

            if ($user->rating) {
                $contestRatings = json_decode($user->rating->contest_ratings, true);
                $user->rating->current_rating = $newRating;
                $contestIdToUpdate = $contest->id;
                $contestRatings = array_filter($contestRatings, function ($rating) use ($contestIdToUpdate) {
                    return $rating['contest_id'] != $contestIdToUpdate;
                });
                $contestRatings[] = [
                    "contest_id" => $contest->id,
                    "rating" => $contestRating,
                ];
                $user->rating->contest_ratings = json_encode($contestRatings);
                $user->rating->save();
            } else {
                Rating::create([
                    'user_id' => $user->id,
                    'current_rating' => $newRating,
                    'contest_ratings' => json_encode([
                        [
                            "contest_id" => $contest->id,
                            "rating" => $contestRating,
                        ]
                    ]),
                ]);
            }
        }

        return $contest->rating;
    }

    // Simplified Elo-based Approach

    function calculateContestRatings($participants, $kFactor = 32)
    {
        $usernames = array_map(fn($p) => $p->username, $participants);
        $users = User::query()->whereIn('name', $usernames)->get()->keyBy('name');

        $newRatings = [];

        foreach ($participants as $index_i => $participant) {
            $currentRating = $users[$participant->username]->rating->current_rating ?? 0;
            $place = $index_i;
            $totalExpectedPerformance = 0;

            foreach ($participants as $index_j => $opponent) {
                if ($index_i != $index_j) {
                    $opponentRating = $users[$opponent->username]->rating->current_rating ?? 0;
                    $totalExpectedPerformance += $this->expectedPerformance($currentRating, $opponentRating);
                }
            }

            $actualPerformance = (count($participants) - $place - 1) / (count($participants) - 1);
            $expectedPerformance = $totalExpectedPerformance / (count($participants) - 1);
            $newRating = $this->calculateNewRating($currentRating, $expectedPerformance, $actualPerformance, $kFactor);

            $newRatings[$index_i] = intval($newRating);
        }

        return $newRatings;
    }

    private function calculateNewRating($currentRating, $expectedPerformance, $actualPerformance, $kFactor = 32)
    {
        return $currentRating + $kFactor * ($actualPerformance - $expectedPerformance);
    }

    private function expectedPerformance($rating, $opponentRating)
    {
        return 1 / (1 + pow(10, ($opponentRating - $rating) / 400));
    }
}
