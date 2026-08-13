<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

use App\Models\Contest;
use App\Models\User;

use App\Http\Resources\User\Contest\ContestDetailResource;

class ContestStandingController extends Controller
{
    /**
     * Get contest standings table with optional official user filtering.
     */
    public function show(Contest $contest, Request $request): JsonResponse|array
    {
        if ($contest->getStatus() === 'notStarted') {
            return response()->json(['error' => 'Bäsleşik başlamady'], 403);
        }

        $problemScores = $contest->problems()
            ->orderBy('id', 'asc')
            ->pluck('score')
            ->toArray();

        $contest->loadMissing('standings');
        $standingsData = $contest->standings;
        $standings = $standingsData->result ?? [];
        
        if (is_string($standings)) {
            $standings = json_decode($standings, true) ?? [];
        }

        $showUnofficial = $request->boolean('unofficial', true);

        if ($showUnofficial && !empty($standings)) {
            $usersHandle = array_column($standings, 'handle');

            $users = User::whereIn('handle', $usersHandle)->get();

            $officialUsernames = [];
            foreach ($users as $user) {
                if ($contest->isUserOfficial($user->id)) {
                    $officialUsernames[$user->handle] = true;
                }
            }

            $standings = array_values(array_filter($standings, function ($standing) use ($officialUsernames) {
                $handle = is_array($standing) ? ($standing['handle'] ?? null) : ($standing->handle ?? null);

                if (is_array($handle)) {
                    $handle = $handle[0] ?? null;
                }

                return $handle && isset($officialUsernames[$handle]);
            }));
        }

        if ($contest->hasSubtasks()) {
            foreach ($standings as &$standing) {
                $problems = is_array($standing) ? ($standing['problems'] ?? []) : ($standing->problems ?? []);

                if (is_array($problems)) {
                    $processedProblems = [];
                    foreach ($problems as $key => $subtaskScores) {
                        $processedProblems[$key] = [
                            'score' => is_array($subtaskScores) ? array_sum($subtaskScores) : (int) $subtaskScores,
                            'accepted_at' => '',
                        ];
                    }

                    if (is_array($standing)) {
                        $standing['problems'] = $processedProblems;
                    } else {
                        $standing->problems = $processedProblems;
                    }
                }
            }
        }

        return [
            'contest' => new ContestDetailResource($contest),
            'problemScores' => $problemScores,
            'standings' => $standings,
        ];
    }
}