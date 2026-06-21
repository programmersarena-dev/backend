<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContestDetailResource;
use App\Http\Resources\ContestListResource;
use App\Http\Resources\SubmissionResource;
use App\Models\Contest;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ContestController extends Controller
{
    public function index()
    {
        $contests = Contest::where('active', true)->orderBy('start_date', 'desc')->get();

        $perPage = 20;
        $page = request()->get('page', 1);
        $paginatedContests = new LengthAwarePaginator(
            $contests->forPage($page, $perPage),
            $contests->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return ContestListResource::collection($paginatedContests);
    }

    public function problems(Contest $contest)
    {
        if ($contest->getStatus() != 'notStarted') {
            $problems = $contest->problems()
                ->select('id', 'name')
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($problem) {
                    $problem->name = $problem->getTranslation("name");
                    $problem->accepted_submissions_count = $problem->getAcceptedSubmissionsCountAttribute();
                    $problem->accepted = $problem->solved();
                    return $problem;
                });
        }
        return [
            'contest' => new ContestDetailResource($contest),
            'problems' => $problems ?? [],
            'acceptableLanguages' => $contest->problems()->first() ? $contest->problems()->first()->acceptableLanguages() : [],
        ];
    }

    public function register(Contest $contest, Request $request)
    {
        $user = Auth::user();
        $contestType = $contest->type->name;

        if ($contestType === 'Duel') {
            $opponent = User::where('name', $request['opponent'])->first();
            if ($contest->isUserRegistered($user->id)) {
                $contest->standings->addUserStanding([$user->name, $opponent->name], $contestType);
                return $this->confirmDuelRegistration($contest);
            }
            if (!$opponent)
                return response()->json(['message' => 'User not found'], 404);
        }

        if (!$contest->active)
            return response('', 404);

        if ($contest->getStatus() !== 'notStarted')
            return response()->json(['message' => 'Bäsleşik başlandygy sebäpli hasaba alyş ýapyldy'], 403);

        if ($contest->isUserOfficial($user->id))
            return response()->json(['message' => 'Resmi gatnaşyjy hökmünde bäsleşige hasaba alynypdyňyz'], 403);

        if ($contest->isUserUnOfficial($user->id))
            return response()->json(['message' => 'Resmi däl gatnaşyjy hökmünde bäsleşige hasaba alynypdyňyz'], 403);

        $participants = json_decode($contest->participantIds, true);

        if ($contestType === 'Classic') {
            if ($contest->official) {
                $participants['unofficial'][] = $user->id;
            } else {
                $participants['official'][] = $user->id;
            }
        } elseif ($contestType === 'Duel') {
            $team = [$user->id, str($opponent->id) . '|X' ?? null];

            if ($contest->official) {
                $participants['unofficial'][] = $team;
            } else {
                $participants['official'][] = $team;
            }
        }

        $contest->update([
            'participantIds' => json_encode($participants),
        ]);

        if ($contestType === 'Classic')
            $contest->standings->addUserStanding($user->name, $contestType);

        return response()->json(['message' => 'Bäsleşige hasaba alyndy'], 202);
    }

    public function unregister(Contest $contest, Request $request)
    {
        $user = Auth::user();
        $contestType = $contest->type->name;

        if (!$contest->active)
            return response('', 404);

        if ($contest->getStatus() !== 'notStarted')
            return response()->json(['message' => 'Bäsleşik başlandygy sebäpli hasapdan çykyş ýapyldy'], 403);

        if (!$contest->isUserRegistered($user->id))
            return response()->json(['message' => 'Bäsleşige gatnaşyjy hökmünde hasaba alynmandy'], 403);

        if ($contest->official && $contest->isUserOfficial($user->id))
            return response()->json(['message' => 'Resmi gatnaşyjy bolanyňyz üçin bu bäsleşikden ýüz dönderip bilmersiňiz'], 403);

        $participants = json_decode($contest->participantIds, true);

        if ($contestType === 'Classic') {
            if ($contest->official) {
                $participants['unofficial'] = array_values(
                    array_filter($participants['unofficial'], fn($id) => $id !== $user->id)
                );
            } else {
                $participants['official'] = array_values(
                    array_filter($participants['official'], fn($id) => $id !== $user->id)
                );
            }
        } elseif ($contestType === 'Duel') {
            if ($contest->official) {
                $participants['unofficial'] = array_values(
                    array_filter($participants['unofficial'], function ($duo) use ($user) {
                        return !in_array($user->id, $duo, true) && !in_array($user->id . '|X', $duo, true);
                    })
                );
            } else {
                $participants['official'] = array_values(
                    array_filter($participants['official'], function ($duo) use ($user) {
                        return !in_array($user->id, $duo, true) && !in_array($user->id . '|X', $duo, true);
                    })
                );
            }
        }


        $contest->update([
            'participantIds' => json_encode($participants),
        ]);

        $contest->standings->removeUserStanding($user->name, $contestType);

        return response()->json(['message' => 'Bäsleşikden üstünlikli çykdy'], 202);
    }

    private function confirmDuelRegistration($contest)
    {
        $user = Auth::user();
        $needsToVerify = false;
        $participants = json_decode($contest->participantIds, true);

        $key = $contest->official ? 'unofficial' : 'official';

        $participants[$key] = array_map(function ($duel) use ($user, &$needsToVerify) {
            if (!is_array($duel) || count($duel) < 2) {
                return $duel;
            }

            $player1Parts = explode('|', $duel[0]);
            $player2Parts = explode('|', $duel[1]);

            $player1Id = $player1Parts[0];
            $needConfirmP1 = count($player1Parts) > 1;

            $player2Id = $player2Parts[0];
            $needConfirmP2 = count($player2Parts) > 1;

            if ((string) $player1Id === (string) $user->id && $needConfirmP1) {
                $needsToVerify = true;
                return [$user->id, $duel[1]];
            } elseif ((string) $player2Id === (string) $user->id && $needConfirmP2) {
                $needsToVerify = true;
                return [$duel[0], $user->id];
            }

            return $duel;
        }, $participants[$key]);

        if (!$needsToVerify) {
            if ($contest->isUserOfficial($user->id)) {
                return response()->json(['message' => 'Resmi gatnaşyjy hökmünde bäsleşige hasaba alynypdyňyz'], 403);
            }
            if ($contest->isUserUnOfficial($user->id)) {
                return response()->json(['message' => 'Resmi däl gatnaşyjy hökmünde bäsleşige hasaba alynypdyňyz'], 403);
            }
        }

        $contest->update([
            'participantIds' => json_encode($participants),
        ]);

        return response()->json(['message' => 'Bäsleşige hasaba alyndy'], 202);
    }

    public function getContestProblemSubmissions(Contest $contest, $problemId, $user)
    {
        $user = User::where('name', $user)->firstOrFail();
        $problem = $contest->problems()->orderBy('id', 'asc')->skip($problemId - 1)->first();
        return SubmissionResource::collection(
            Submission::query()
                ->where('problem_id', $problem->id)
                ->where('user_id', $user->id)
                ->where('created_at', '>=', $contest->start_date)
                ->where('created_at', '<=', $contest->end_date)
                ->get()
        );
    }
}
