<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

use App\Models\Contest;
use App\Models\ContestRating;
use App\Models\User;

use App\Http\Resources\User\Contest\ContestRatingResource;

class ContestRatingController extends Controller
{
    public function index()
    {
        $users = User::where('user_type', 'user')
            ->withCount('contestRatings')
            ->orderByDesc('current_rating')
            ->paginate(50);

        return ContestRatingResource::collection($users);
    }

    public function store(Contest $contest)
    {
        $standings = $contest->standings;
        $participants = $standings->result ?? [];

        $handles = array_map(fn ($p) => is_array($p) ? $p['handle'] : $p->handle, $participants);
        $users = User::query()->whereIn('handle', $handles)->get()->keyBy('handle');

        $newRatings = $this->calculateContestRatings($participants, $users);

        DB::transaction(function () use ($contest, $participants, $users, $newRatings) {
            foreach ($participants as $index => $participant) {
                $handle = is_array($participant) ? $participant['handle'] : $participant->handle;
                $problems = is_array($participant) ? ($participant['problems'] ?? []) : ($participant->problems ?? []);
                $solved = collect($problems)->filter(fn ($p) => (is_array($p) ? $p['status'] : $p->status) === 'AC')->count();

                $user = $users->get($handle);

                if (!$user) {
                    continue;
                }

                $oldRating = $user->current_rating;
                $newRating = $newRatings[$index];

                ContestRating::updateOrCreate(
                    ['user_id' => $user->id, 'contest_id' => $contest->id],
                    [
                        'rank'       => $index + 1,
                        'solved'     => $solved,
                        'old_rating' => $oldRating,
                        'new_rating' => $newRating,
                    ]
                );

                $user->update([
                    'current_rating' => $newRating,
                    'max_rating'     => max($user->max_rating, $newRating),
                ]);
            }
        });

        return ContestRating::with('user:id,name')
            ->where('contest_id', $contest->id)
            ->orderBy('rank')
            ->get();
    }

function calculateContestRatings($participants, $users, $kFactor = 32)
{
    $newRatings = [];

    foreach ($participants as $index_i => $participant) {
        $handle_i = is_array($participant) ? $participant['handle'] : $participant->handle;
        $currentRating = $users[$handle_i]->current_rating ?? 0;
        $place = $index_i;
        $totalExpectedPerformance = 0;

        foreach ($participants as $index_j => $opponent) {
            if ($index_i != $index_j) {
                $handle_j = is_array($opponent) ? $opponent['handle'] : $opponent->handle;
                $opponentRating = $users[$handle_j]->current_rating ?? 0;
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
