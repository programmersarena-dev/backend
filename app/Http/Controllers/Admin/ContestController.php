<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ContestNotification;
use Illuminate\Http\Request;

use App\Models\Contest;
use App\Models\ContestType;
use App\Models\ContestStanding;
use App\Models\User;

use App\Http\Requests\StoreContestRequest;
use App\Http\Requests\UpdateContestRequest;

use App\Http\Resources\Admin\Contest\ContestDetailResource;
use App\Http\Resources\Admin\Contest\ContestListResource;

class ContestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $now = Carbon::now('UTC');

        $searchName = $request->query('searchName', '');
        $contests = Contest::where(fn($q) => $q->where('name', 'like', '%' . $searchName . '%'))->orderBy('start_date', 'desc')->get();

        $contests->each(function ($contest) use ($now) {
            $contest->status = $contest->end_date < $now ? 'ended' : ($now < $contest->start_date ? 'notStarted' : 'started');
        });

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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContestRequest $request)
    {
        $data = $request->validated();

        $users = User::all()->keyBy('name');
        $type = ContestType::where('name', $data['type'])->firstOrFail();

        [$hours, $minutes] = explode(':', $data['duration']);
        $durationMinutes = ((int) $hours * 60) + (int) $minutes;

        $contest = Contest::create([
            'type_id' => $type->id,
            'name' => $data['name'],
            'start_date' => Carbon::parse($data['start_date'])->setTimezone('UTC')->toIso8601String(),
            'duration_minutes' => $durationMinutes,
            'official' => $data['official'],
            'active' => $data['active'],
        ]);

        $this->syncAuthors($contest, $data['authors'], $users);
        $this->syncParticipants($contest, $data['participants'], $data['type'], $users);

        ContestStanding::create([
            'contest_id' => $contest->id,
        ]);

        return response()->json([
            'message' => 'üstünlikli döredildi',
            'contest' => new ContestDetailResource($contest),
        ], 202);
    }

    /**
     * Display the specified resource.
     */
    public function show(Contest $contest)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contest $contest)
    {
        return new ContestDetailResource($contest);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContestRequest $request, Contest $contest)
    {
        $data = $request->validated();

        $users = User::all()->keyBy('name');
        $type = ContestType::where('name', $data['type'])->firstOrFail();

        [$hours, $minutes] = explode(':', $data['duration']);
        $durationMinutes = ((int) $hours * 60) + (int) $minutes;

        $contest->update([
            'type_id' => $type->id,
            'name' => $data['name'],
            'start_date' => Carbon::parse($data['start_date'])->setTimezone('UTC')->toIso8601String(),
            'duration_minutes' => $durationMinutes,
            'official' => $data['official'],
            'active' => $data['active'],
        ]);

        $this->syncAuthors($contest, $data['authors'], $users);
        $this->syncParticipants($contest, $data['participants'], $data['type'], $users);

        return response()->json([
            'message' => 'Üstünlikli üýtgedildi',
            'contest' => new ContestDetailResource($contest),
        ], 202);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contest $contest)
    {
        if ($contest->start_date <= Carbon::now('UTC')) {
            return response()->json(['message' => 'Bolan bäsleşigi pozup bolanok'], 404);
        }

        $contest->standings->delete();

        $problems = $contest->problems;
        foreach ($problems as $problem) {
            $problem->delete();
        }

        $contest->delete();

        return response()->json([
            'message' => 'Üstünlikli pozuldy'
        ], 202);
    }

    public function notifyUsers(Contest $contest)
    {
        if ($contest) {
            if ($contest->getStatus() != 'notStarted') {
                return response()->json([
                    'message' => 'Bäsleşik eýýam başlady ýa gutardy'
                ], 400);
            }
            $users = User::all();
            Notification::send($users, new ContestNotification($contest));
            return response()->json([
                'message' => 'Ulanyjylara üstünlikli duýduryldy'
            ], 200);
        }

        return response()->json([
            'message' => 'Bäsleşik tapylmady'
        ], 404);
    }

    /**
     * Sync the contest_author pivot from a list of usernames.
     * Requires Contest::authors(): belongsToMany(User::class, 'contest_author').
     */
    private function syncAuthors(Contest $contest, array $authorNames, $users): void
    {
        $authorIds = collect($authorNames)
            ->map(fn ($userName) => $users[$userName]->id ?? null)
            ->filter()
            ->values();

        $contest->authors()->sync($authorIds);
    }

    /**
     * Sync the contest_user pivot (participants) from the official/unofficial
     * username lists, setting is_official and, for Duel contests, opponent_id
     * on both sides of each pairing.
     * Requires Contest::participants(): belongsToMany(User::class, 'contest_user')
     *   ->withPivot(['is_official', 'opponent_id', 'old_rating', 'rating_change'])
     *   ->withTimestamps();
     */
    private function syncParticipants(Contest $contest, array $participants, string $type, $users): void
    {
        $isDuel = $type === 'Duel';
        $sync = [];

        foreach (['official', 'unofficial'] as $group) {
            $isOfficial = $group === 'official';

            foreach ($participants[$group] ?? [] as $entry) {
                if ($isDuel) {
                    [$nameA, $nameB] = $entry;
                    $userA = $users[$nameA] ?? null;
                    $userB = $users[$nameB] ?? null;

                    if ($userA) {
                        $sync[$userA->id] = [
                            'is_official' => $isOfficial,
                            'opponent_id' => $userB->id ?? null,
                        ];
                    }
                    if ($userB) {
                        $sync[$userB->id] = [
                            'is_official' => $isOfficial,
                            'opponent_id' => $userA->id ?? null,
                        ];
                    }
                } else {
                    $user = $users[$entry] ?? null;
                    if ($user) {
                        $sync[$user->id] = [
                            'is_official' => $isOfficial,
                            'opponent_id' => null,
                        ];
                    }
                }
            }
        }

        // sync() replaces the full pivot set for this contest with $sync.
        // old_rating / rating_change are intentionally left untouched here —
        // they belong to the post-contest rating calculation step, not
        // contest creation/editing.
        $contest->participants()->sync($sync);
    }
}
