<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Submission;
use App\Models\Contest;
use App\Models\Problem;

use App\Http\Resources\User\Problem\ProblemListResource;
use App\Http\Resources\User\Problem\ProblemDetailResource;
use App\Http\Resources\User\Contest\ContestDetailResource;
use App\Http\Resources\User\UserResource;

class ProblemController extends Controller
{
    public function index(Request $request)
    {
        $hideSolved = $request->query('hideSolved') === 'true';
        $difficultyMin = $request->query('difficultyMin');
        $difficultyMax = $request->query('difficultyMax');

        $now = Carbon::now('UTC');

        $problems = Problem::whereHas('contest', function ($query) {
            $query->where('active', 1);
        })->with('contest')->get();

        $filteredProblems = $problems->filter(function ($problem) use ($now) {
            return $problem->contest->end_date->lte($now);
        })->sortByDesc('id');

        if ($hideSolved) {
            $filteredProblems = $filteredProblems->filter(function ($problem) {
                return !$problem->accepted;
            });
        }

        if ($difficultyMin !== null) {
            $filteredProblems = $filteredProblems->where('score', '>=', $difficultyMin);
        }

        if ($difficultyMax !== null) {
            $filteredProblems = $filteredProblems->where('score', '<=', $difficultyMax);
        }

        if ($request->order == 'BY_RATING_ASC') {
            $filteredProblems = $filteredProblems->sortBy('score');
        } elseif ($request->order == 'BY_RATING_DESC') {
            $filteredProblems = $filteredProblems->sortByDesc('score');
        } elseif ($request->order == 'BY_SOLVED_ASC') {
            $filteredProblems = $filteredProblems->sortBy('accepted_submissions_count');
        } elseif ($request->order == 'BY_SOLVED_DESC') {
            $filteredProblems = $filteredProblems->sortByDesc('accepted_submissions_count');
        }

        $perPage = 100;
        $page = request()->get('page', 1);
        $paginatedProblems = new LengthAwarePaginator(
            $filteredProblems->forPage($page, $perPage),
            $filteredProblems->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return ProblemListResource::collection($paginatedProblems);
    }

    public function show(Contest $contest, $char)
    {
        $problem = $contest->getProblemByCharacter($char);
        
        return [
            'contest' => new ContestDetailResource($contest),
            'problem' => new ProblemDetailResource($problem),
        ];
    }

    public function standings()
    {
        $paginatedUsers = User::query()
            ->where('user_type', 'user')
            ->with(['profile.country', 'contestRatings'])
            ->withCount([
                'submissions as accepted_problems_count' => function ($query) {
                    $query->whereIn('status', Submission::ACCEPTED_STATUSES)
                        ->select(DB::raw('count(distinct problem_id)'));
                }
            ])
            ->orderByDesc('accepted_problems_count')
            ->paginate(100);

        return UserResource::collection($paginatedUsers);
    }

    public function getAttachments(Contest $contest, $char)
    {
        if ($contest->hasAttachments()) {
            return response()->json(['message' => 'Not found attachments']);
        }

        $problem = $contest->problems()->orderBy('id', 'asc')->skip(ord($char) - ord('A'))->first();

        if (!$problem) {
            return response()->json(['message' => 'Mesele tapylmady'], 404);
        }

        return $problem->downloadAttachments();
    }
}
