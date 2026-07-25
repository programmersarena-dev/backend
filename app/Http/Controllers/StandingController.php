<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContestDetailResource;
use App\Http\Resources\ProblemsetStandingsResource;
use App\Http\Resources\UserResource;
use App\Models\Contest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class StandingController extends Controller
{
    /**
     * Get contest standings table with optional official user filtering.
     */
    public function getByContest(Contest $contest, Request $request): JsonResponse|array
    {
        if ($contest->getStatus() === 'notStarted') {
            return response()->json(['error' => 'Bäsleşik başlamady'], 403);
        }

        // Fetch problem scores efficiently
        $problemScores = $contest->problems()
            ->orderBy('id', 'asc')
            ->pluck('score')
            ->toArray();

        // Load standings relationship
        $contest->loadMissing('standings');
        $standingsData = $contest->standings;
        $standings = $standingsData ? (json_decode($standingsData->result) ?? []) : [];

        // Filter official participants if unofficial parameter is false
        $isUnofficial = $request->boolean('unofficial', true);
        if (!$isUnofficial && !empty($standings)) {
            $usernames = array_column($standings, 'username');

            // Single query to retrieve all matching users
            $users = User::whereIn('name', $usernames)->get();

            // Filter official user IDs once
            $officialUserIds = $users->filter(fn(User $user) => $contest->isUserOfficial($user->id))
                ->pluck('id')
                ->flip()
                ->toArray();

            // Map users by name for O(1) lookups
            $usersByName = $users->keyBy('name');

            $standings = array_values(array_filter($standings, function ($standing) use ($usersByName, $officialUserIds) {
                $user = $usersByName->get($standing->username);
                return $user && isset($officialUserIds[$user->id]);
            }));
        }

        // Aggregate subtask scores if applicable
        if ($contest->hasSubtasks()) {
            foreach ($standings as &$standing) {
                if (isset($standing->problems) && is_array($standing->problems)) {
                    $standing->problems = array_map(fn($problems) => [
                        'score' => is_array($problems) ? array_sum($problems) : (int) $problems,
                        'accepted_at' => '',
                    ], $standing->problems);
                }
            }
        }

        return [
            'contest' => new ContestDetailResource($contest),
            'problemScores' => $problemScores,
            'standings' => $standings,
        ];
    }

    /**
     * Get global problemset leaderboard paginated directly via SQL database query.
     */
    public function usersProblemStandings(): AnonymousResourceCollection
    {
        $paginatedUsers = User::query()
            ->where('user_type', 'user')
            ->with(['profile.country', 'rating'])
            ->withCount([
                'submissions as accepted_problems_count' => function ($query) {
                    $query->whereIn('status', User::ACCEPTED_STATUSES)
                        ->select(DB::raw('count(distinct problem_id)'));
                }
            ])
            ->orderByDesc('accepted_problems_count')
            ->paginate(100);

        return UserResource::collection($paginatedUsers);
    }
}
