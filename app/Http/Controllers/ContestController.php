<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContestDetailResource;
use App\Http\Resources\ContestListResource;
use App\Http\Resources\SubmissionResource;
use App\Models\Contest;
use App\Models\Submission;
use App\Models\User;
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
        $problems = [];

        if ($contest->getStatus() !== 'notStarted') {
            $problems = $contest->problems()
                ->select('id', 'name', 'contest_id')
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($problem) {
                    $problem->name = $problem->getTranslation('name');
                    $problem->accepted_submissions_count = $problem->getAcceptedSubmissionsCountAttribute();
                    $problem->accepted = $problem->solved();
                    return $problem;
                });
        }

        $firstProblem = $contest->problems()->first();

        return [
            'contest' => new ContestDetailResource($contest),
            'problems' => $problems,
            'acceptableLanguages' => $firstProblem ? $firstProblem->acceptableLanguages() : [],
        ];
    }

    public function register(Contest $contest, Request $request): JsonResponse|Response
    {
        if (!$contest->active) {
            return response('', 404);
        }

        if ($contest->getStatus() !== 'notStarted') {
            return response()->json(['message' => __('contest.registration_closed')], 403);
        }

        $user = Auth::user();
        $contestType = $contest->type?->name;

        if ($contest->isUserOfficial($user->id)) {
            return response()->json(['message' => __('contest.already_official')], 403);
        }
        if ($contest->isUserUnOfficial($user->id)) {
            return response()->json(['message' => __('contest.already_unofficial')], 403);
        }

        $isOfficialParticipant = !$contest->official;

        if ($contestType === 'Duel') {
            $opponentName = $request->input('opponent');
            $opponent = User::where('name', $opponentName)->first(['id']);

            if (!$opponent) {
                return response()->json(['message' => __('contest.user_not_found')], 404);
            }

            $existingInvite = $contest->participants()
                ->where('user_id', $opponent->id)
                ->where('opponent_id', $user->id)
                ->first();

            return DB::transaction(function () use ($contest, $user, $opponent, $isOfficialParticipant, $existingInvite) {
                if ($existingInvite) {
                    $contest->participants()->updateExistingPivot($opponent->id, ['opponent_id' => $user->id]);
                    $contest->participants()->attach($user->id, [
                        'is_official' => $isOfficialParticipant,
                        'opponent_id' => $opponent->id
                    ]);

                    $contest->standings?->addUserStanding([$user->name, $existingInvite->name], 'Duel');
                    return response()->json(['message' => __('contest.registered')], 202);
                }

                $contest->participants()->attach($user->id, [
                    'is_official' => $isOfficialParticipant,
                    'opponent_id' => $opponent->id
                ]);

                return response()->json(['message' => __('contest.registered_waiting')], 202);
            });
        }

        DB::transaction(function () use ($contest, $user, $isOfficialParticipant) {
            $contest->participants()->attach($user->id, [
                'is_official' => $isOfficialParticipant
            ]);
            $contest->standings?->addUserStanding($user->name, 'Classic');
        });

        return response()->json(['message' => __('contest.registered')], 202);
    }

    public function unregister(Contest $contest): JsonResponse|Response
    {
        if (!$contest->active) {
            return response('', 404);
        }

        if ($contest->getStatus() !== 'notStarted') {
            return response()->json(['message' => __('contest.unregistration_closed')], 403);
        }

        $user = Auth::user();

        if (!$contest->isUserRegistered($user->id)) {
            return response()->json(['message' => __('contest.not_registered')], 403);
        }

        if ($contest->official && $contest->isUserOfficial($user->id)) {
            return response()->json(['message' => __('contest.cannot_unregister')], 403);
        }

        DB::transaction(function () use ($contest, $user) {
            $contest->participants()->detach($user->id);
            $contest->standings?->removeUserStanding($user->name, $contest->type?->name);
        });

        return response()->json(['message' => __('contest.unregistered')], 202);
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
