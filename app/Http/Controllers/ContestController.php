<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContestDetailResource;
use App\Http\Resources\ContestListResource;
use App\Http\Resources\SubmissionResource;
use App\Models\Contest;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContestController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $paginatedContests = Contest::query()
            ->where('active', true)
            ->with([
                'type',
                'authors:id,name',
                'participants:id,name'
            ])
            ->orderBy('start_date', 'desc')
            ->paginate(20);

        return ContestListResource::collection($paginatedContests);
    }

    public function problems(Contest $contest): array
    {
        $problems = collect();

        if ($contest->getStatus() !== 'notStarted') {
            $user = Auth::guard('sanctum')->user();

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

            \Log::info($problems);

            $problems->transform(function ($problem) use ($solvedProblemIds) {
                $problem->name = $problem->getTranslation('name');
                $problem->accepted = $solvedProblemIds->has($problem->id);

                return $problem;
            });
        }

        $acceptableLanguages = (new Problem)->acceptableLanguages();

        return [
            'contest' => new ContestDetailResource($contest),
            'problems' => $problems,
            'acceptableLanguages' => $acceptableLanguages,
        ];
    }

    public function register(Contest $contest, Request $request): JsonResponse
    {
        if (!$contest->active) {
            return response()->json(['message' => __('contest.not_found')], 404);
        }

        if ($contest->getStatus() !== 'notStarted') {
            return response()->json(['message' => __('contest.registration_closed')], 403);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => __('auth.unauthorized')], 401);
        }

        $contestType = $contest->type?->name;

        if ($contest->isUserRegistered($user->id)) {
            return response()->json(['message' => __('contest.already_registered')], 403);
        }

        $isOfficial = !$contest->official;

        if ($contestType === 'Duel') {
            return $this->registerForDuel($contest, $user, $request, $isOfficial);
        }

        return $this->registerForClassic($contest, $user, $isOfficial);
    }

    private function registerForClassic(Contest $contest, User $user, bool $isOfficial): JsonResponse
    {
        try {
            DB::transaction(function () use ($contest, $user, $isOfficial) {
                // Attach participant
                $contest->participants()->attach($user->id, [
                    'is_official' => $isOfficial,
                ]);

                // Ensure standings exist and add user
                $this->ensureStandingsExist($contest);
                $contest->standings->addUserStanding($user->name, 'Classic');
            });

            return response()->json(['message' => __('contest.registered')], 200);
        } catch (UniqueConstraintViolationException $e) {
            // Should not happen because we already checked, but just in case
            return response()->json(['message' => __('contest.already_registered')], 409);
        } catch (\Exception $e) {
            \Log::error('Classic registration failed', ['user' => $user->id, 'contest' => $contest->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => __('contest.registration_failed')], 500);
        }
    }

    /**
     * Register a user for a Duel contest.
     */
    private function registerForDuel(Contest $contest, User $user, Request $request, bool $isOfficial): JsonResponse
    {
        $opponentName = $request->input('opponent');
        if (empty($opponentName)) {
            return response()->json(['message' => __('contest.opponent_required')], 422);
        }

        // Find opponent
        $opponent = User::where('name', $opponentName)->first();
        if (!$opponent) {
            return response()->json(['message' => __('contest.user_not_found')], 404);
        }

        // Prevent self-duel
        if ($opponent->id === $user->id) {
            return response()->json(['message' => __('contest.self_duel_not_allowed')], 422);
        }

        // Check if opponent is already registered in this contest
        if ($contest->isUserRegistered($opponent->id)) {
            return response()->json(['message' => __('contest.opponent_already_registered')], 422);
        }

        try {
            return DB::transaction(function () use ($contest, $user, $opponent, $isOfficial) {
                // Lock both potential participant rows to avoid race conditions
                $lockedUsers = $contest->participants()
                    ->whereIn('user_id', [$user->id, $opponent->id])
                    ->lockForUpdate()
                    ->get();

                // Check if there is a pending invitation from opponent to user
                $existingInvite = $contest->participants()
                    ->where('user_id', $opponent->id)
                    ->where('opponent_id', $user->id)
                    ->first();

                // Ensure standings exist
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
                    $contest->standings->addUserStanding($user->name, 'Duel');
                    $contest->standings->addUserStanding($opponent->name, 'Duel');

                    return response()->json(['message' => __('contest.registered_duel_accepted')], 200);
                }

                // New duel request: register the user with opponent_id, opponent is not yet registered
                $contest->participants()->attach($user->id, [
                    'is_official' => $isOfficial,
                    'opponent_id' => $opponent->id,
                ]);

                // Add only the current user to standings (opponent will be added when they accept)
                $contest->standings->addUserStanding($user->name, 'Duel');

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
        if (!$contest->active) {
            return response()->json(['message' => __('contest.not_found')], 404);
        }

        if ($contest->getStatus() !== 'notStarted') {
            return response()->json(['message' => __('contest.unregistration_closed')], 403);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => __('auth.unauthorized')], 401);
        }

        if (!$contest->isUserRegistered($user->id)) {
            return response()->json(['message' => __('contest.not_registered')], 403);
        }

        if ($contest->official && $contest->isUserOfficial($user->id)) {
            return response()->json(['message' => __('contest.cannot_unregister_official')], 403);
        }

        try {
            DB::transaction(function () use ($contest, $user) {
                $contestType = $contest->type?->name;

                // For Duel: remove opponent_id references from other participants
                if ($contestType === 'Duel') {
                    // Find the opponent of this user (if any)
                    $participant = $contest->participants()
                        ->where('user_id', $user->id)
                        ->first(['opponent_id']);

                    if ($participant && $participant->opponent_id) {
                        // Clear opponent_id from that opponent's row (if they are still registered)
                        $contest->participants()
                            ->where('user_id', $participant->opponent_id)
                            ->update(['opponent_id' => null]);
                    }

                    // Also, if someone has this user as opponent, clear it
                    $contest->participants()
                        ->where('opponent_id', $user->id)
                        ->update(['opponent_id' => null]);
                }

                // Remove the user from participants
                $contest->participants()->detach($user->id);

                // Remove from standings if they exist
                if ($contest->standings) {
                    $contest->standings->removeUserStanding($user->name, $contestType);
                }
            });

            return response()->json(['message' => __('contest.unregistered')], 200);
        } catch (\Exception $e) {
            \Log::error('Unregistration failed', ['user' => $user->id, 'contest' => $contest->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => __('contest.unregistration_failed')], 500);
        }
    }

    /**
     * Ensure that a standings record exists for the contest.
     * Creates one if missing.
     */
    private function ensureStandingsExist(Contest $contest): void
    {
        if (!$contest->standings) {
            $contest->standings()->create([
                // fill with any required fields, e.g., 'contest_id' is auto-assigned
                // add other defaults if needed
            ]);
            $contest->refresh(); // reload relationship
        }
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

        return SubmissionResource::collection($submissions);
    }
}
