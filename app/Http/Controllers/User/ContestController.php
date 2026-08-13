<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

use App\Models\Contest;
use App\Models\Problem;
use App\Models\Submission;

use App\Http\Resources\User\Contest\ContestListResource;
use App\Http\Resources\User\Contest\ContestDetailResource;
use App\Http\Resources\User\Problem\ProblemListResource;
use App\Http\Resources\User\Submission\SubmissionListResource;

class ContestController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $now = now();
        $activeBuffer = now()->subHours(24);

        $paginatedContests = Contest::query()
            ->where('active', true)
            ->with([
                'type',
                'authors:id,name',
                'participants:id,name'
            ])
            ->orderByRaw("
                CASE 
                    -- Active/Ongoing or Upcoming
                    WHEN start_date >= ? THEN 1
                    -- Finished (started more than 24h ago)
                    ELSE 2
                END ASC
            ", [$activeBuffer])
            ->orderBy('start_date', 'asc')
            ->paginate(20);

        return ContestListResource::collection($paginatedContests);
    }

    public function show(Contest $contest): array
    {
        $problems = collect();

        $user = Auth::guard('sanctum')->user() ?? null;

        $problems = $contest->problems()
            ->with(['translations'])
            ->withCount([
                'submissions as accepted_submissions_count' => function ($query) {
                    $query->accepted()->select(DB::raw('count(distinct user_id)'));
                }
            ])
            ->orderBy('id', 'asc')
            ->get();

        $solvedProblemIds = ($user && $problems->isNotEmpty())
            ? $user->submissions()
                ->whereIn('problem_id', $problems->pluck('id'))
                ->accepted()
                ->pluck('problem_id')
                ->unique()
                ->flip()
            : collect();

        $problems->transform(function ($problem) use ($solvedProblemIds) {
            $problem->name = $problem->getTranslation('name');
            $problem->accepted = $solvedProblemIds->has($problem->id);

            return $problem;
        });

        return [
            'contest' => new ContestDetailResource($contest),
            'problems' => ProblemListResource::collection($problems)
        ];
    }

    public function register(Contest $contest, Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        $contestType = $contest->type?->name;

        if ($contest->isUserRegistered($user->id)) {
            return response()->json(['message' => __('contest.already_registered')], 403);
        }

        $isOfficialUser = $contest->official ? false : true;

        if ($contestType === 'Duel') {
            return $this->registerForDuel($contest, $user, $request, $isOfficialUser);
        }

        return $this->registerForClassic($contest, $user, $isOfficialUser);
    }

    private function registerForClassic(Contest $contest, User $user, bool $isOfficialUser): JsonResponse
    {
        if ($contest->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => __('contest.already_registered')], 409);
        }

        DB::transaction(function () use ($contest, $user, $isOfficialUser) {
            $contest->participants()->attach($user->id, [
                'is_official' => $isOfficialUser,
            ]);
        });

        return response()->json(['message' => __('contest.registered')], 200);
    }

    /**
     * Register a user for a Duel contest.
     * TODO: optimize code
     */
    private function registerForDuel(Contest $contest, User $user, Request $request, bool $isOfficial): JsonResponse
    {
        $opponentHandle = $request->input('opponent');
        if (empty($opponentHandle)) {
            return response()->json(['message' => __('contest.opponent_required')], 422);
        }

        $opponent = User::where('handle', $opponentHandle)->first();
        if (!$opponent) {
            return response()->json(['message' => __('contest.user_not_found')], 404);
        }
        if ($opponent->id === $user->id) {
            return response()->json(['message' => __('contest.self_duel_not_allowed')], 422);
        }
        if ($contest->isUserRegistered($opponent->id)) {
            return response()->json(['message' => __('contest.opponent_already_registered')], 422);
        }

        try {
            return DB::transaction(function () use ($contest, $user, $opponent, $isOfficial) {
                $lockedUsers = $contest->participants()
                    ->whereIn('user_id', [$user->id, $opponent->id])
                    ->lockForUpdate()
                    ->get();

                $existingInvite = $contest->participants()
                    ->where('user_id', $opponent->id)
                    ->where('opponent_id', $user->id)
                    ->first();

                $this->ensureStandingsExist($contest);

                if ($existingInvite) {
                    // Accept the invitation: update opponent's row and attach current user
                    $contest->participants()->updateExistingPivot($opponent->id, [
                        'opponent_id' => $user->id,
                    ]);

                    $contest->participants()->attach($user->id, [
                        'is_official' => $isOfficial,
                        'opponent_id' => $opponent->id,
                    ]);

                    // Add both users to standings (if not already present)
                    app(StandingService::class)->addUserStanding($contest->standings, [$user->name, $opponent->name]);

                    return response()->json(['message' => __('contest.registered_duel_accepted')], 200);
                }

                // New duel request: register the user with opponent_id, opponent is not yet registered
                $contest->participants()->attach($user->id, [
                    'is_official' => $isOfficial,
                    'opponent_id' => $opponent->id,
                ]);

                // Add only the current user to standings (opponent will be added when they accept)
                app(StandingService::class)->addUserStanding($contest->standings, $user->name);

                return response()->json(['message' => __('contest.registered_waiting')], 202);
            });
        } catch (UniqueConstraintViolationException $e) {
            return response()->json(['message' => __('contest.already_registered')], 409);
        } catch (\Exception $e) {
            \Log::error('Duel registration failed', [
                'user' => $user->id,
                'opponent' => $opponent->id,
                'contest' => $contest->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => __('contest.registration_failed')], 500);
        }
    }

    public function unregister(Contest $contest): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        if (!$contest->isUserRegistered($user->id)) {
            return response()->json(['message' => __('contest.not_registered')], 403);
        }

        if ($contest->official && $contest->isUserOfficial($user->id)) {
            return response()->json(['message' => __('contest.cannot_unregister_official')], 403);
        }

        DB::transaction(function () use ($contest, $user) {
            $contestType = $contest->type?->name;

            // TODO: optimize this code
            if ($contestType === 'Duel') {
                $participant = $contest->participants()
                    ->where('user_id', $user->id)
                    ->first(['opponent_id']);

                if ($participant && $participant->opponent_id) {
                    $contest->participants()
                        ->where('user_id', $participant->opponent_id)
                        ->update(['opponent_id' => null]);
                }

                $contest->participants()
                    ->where('opponent_id', $user->id)
                    ->update(['opponent_id' => null]);
            }

            $contest->participants()->detach($user->id);
        });

        return response()->json(['message' => __('contest.unregistered')], 200);
    }

    public function submit(Contest $contest): array
    {
        return [
            'problems' => $contest->problems->map->only(['char', 'name']),
            'acceptable_languages' => $contest->acceptableLanguages(),
        ];
    }

    public function getContestProblemSubmissions(Contest $contest, int $problemId, string $username): AnonymousResourceCollection
    {
        $targetUser = User::where('name', $username)->firstOrFail(['id']);

        $problem = $contest->problems()
            ->orderBy('id', 'asc')
            ->offset($problemId - 1)
            ->firstOrFail(['id']);

        $submissions = Submission::query()
            ->where('problem_id', $problem->id)
            ->where('user_id', $targetUser->id)
            ->whereBetween('created_at', [$contest->start_date, $contest->end_date])
            ->get();

        return SubmissionListResource::collection($submissions);
    }
}
