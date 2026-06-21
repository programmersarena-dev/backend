<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContestDetailResource;
use App\Http\Resources\UserProblemsStandingResource;
use App\Models\Contest;
use App\Models\Submission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Resources\ProblemsetStandingsResource;

class StandingController extends Controller
{
    public function getByContest(Contest $contest, Request $request)
    {
        if (!$contest) {
            return response()->json(['message' => 'Bäsleşik tapylmady'], 404);
        }

        if ($contest->getStatus() == 'notStarted') {
            return response()->json(['error' => 'Bäsleşik başlamady'], 403);
        }

        $problemScores = $contest->problems()->orderBy('id', 'asc')->pluck('score')->toArray();
        $standings = $contest->standings;

        $standings = $standings ? json_decode($standings->result) : [];

        $unofficial = $request['unofficial'];
        if ($unofficial == "false") {
            $users = User::whereIn('name', array_column($standings, 'username'))
                ->get();
            $officialUserIds = $users->filter(function ($user) use ($contest) {
                return $contest->isUserOfficial($user->id);
            })->pluck('id')->toArray();

            $standings = array_filter($standings, function ($standing) use ($officialUserIds) {
                $user = User::where('name', $standing->username)->first();
                return $user && in_array($user->id, $officialUserIds);
            });

            $standings = array_values($standings);
        }

        if ($contest->hasSubtasks()) {
            foreach ($standings as &$standing) {
                $standing->problems = array_map(function ($problems) {
                    return ['score' => array_sum($problems), 'accepted_at' => ''];
                }, $standing->problems);
            }
        }

        return [
            'contest' => new ContestDetailResource($contest),
            'problemScores' => $problemScores,
            'standings' => $standings,
        ];
    }

    public function usersProblemStandings()
    {
        $users = User::all()->where('user_type', '=', 'user')->sortByDesc('accepted_problems_count');

        $page = request()->get('page', 1);
        $perPage = 100;
        $paginatedUsers = new \Illuminate\Pagination\LengthAwarePaginator(
            $users->forPage($page, $perPage),
            $users->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return ProblemsetStandingsResource::collection($paginatedUsers);
    }
}
