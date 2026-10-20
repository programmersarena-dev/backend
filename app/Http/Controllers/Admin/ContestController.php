<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContestRequest;
use App\Http\Requests\UpdateContestRequest;
use App\Http\Resources\Admin\Contest\ContestDetailResource;
use App\Http\Resources\Admin\Contest\ContestListResource;
use App\Jobs\NotifyContestUsers;
use App\Models\Contest;
use App\Models\ContestStanding;
use App\Models\ContestType;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchName = $request->query('searchName', '');
        $paginatedContests = Contest::with(['type', 'authors', 'participants'])
            ->where('name', 'like', '%'.$searchName.'%')->orderByDesc('start_date')
            ->paginate(20)->withQueryString();

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

        return DB::transaction(function () use ($data) {
            $users = $this->selectedUsers($data);
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
        });
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

        return DB::transaction(function () use ($data, $contest) {
            $users = $this->selectedUsers($data);
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
        });
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
            'message' => 'Üstünlikli pozuldy',
        ], 202);
    }

    public function notifyUsers(Contest $contest)
    {
        if ($contest) {
            if ($contest->status != 'Pending') {
                return response()->json([
                    'message' => 'Bäsleşik eýýam başlady ýa gutardy',
                ], 400);
            }
            NotifyContestUsers::dispatch($contest->id)
                ->onConnection(config('queue.notifications_connection'));

            return response()->json([
                'message' => 'Notifications queued.',
            ], 202);
        }

        return response()->json([
            'message' => 'Bäsleşik tapylmady',
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

    private function selectedUsers(array $data)
    {
        $handles = collect($data['authors'] ?? [])->merge(
            collect($data['participants'] ?? [])->flatten()
        )->unique()->values();
        $users = User::whereIn('handle', $handles)->get(['id', 'handle'])->keyBy('handle');
        if ($users->count() !== $handles->count()) {
            throw ValidationException::withMessages(['participants' => 'Select existing user handles.']);
        }

        return $users;
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
        $isICPC = $type === 'ICPC';
        $sync = [];

        foreach (['official', 'unofficial'] as $group) {
            $isOfficial = $group === 'official';
            $entries = $participants[$group] ?? [];

            if ($isICPC) {
                $flatEntries = [];
                foreach ($entries as $entry) {
                    if (is_array($entry)) {
                        $flatEntries = array_merge($flatEntries, $entry);
                    } else {
                        $flatEntries[] = $entry;
                    }
                }

                foreach (array_chunk($flatEntries, 4) as $chunk) {
                    foreach ($chunk as $userName) {
                        $user = $users[$userName] ?? null;
                        if ($user) {
                            $sync[$user->id] = [
                                'is_official' => $isOfficial,
                                'opponent_id' => null,
                            ];
                        }
                    }
                }

                continue;
            }

            foreach ($entries as $entry) {
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

        $contest->participants()->sync($sync);
    }

    private function syncICPCGroups(Contest $contest, array $entries, $users, bool $isOfficial): void
    {
        $groupIndex = 1;
        $chunks = array_values(array_filter(array_chunk($entries, 3)));

        foreach ($chunks as $chunk) {
            $group = Group::updateOrCreate(
                [
                    'contest_id' => $contest->id,
                    'name' => 'Group '.$groupIndex,
                ],
                [
                    'contest_id' => $contest->id,
                    'name' => 'Group '.$groupIndex,
                ]
            );

            $group->members()->sync(
                $users->filter(fn ($u) => in_array($u->name, $chunk))->map(fn ($u) => [
                    'contest_id' => $contest->id,
                ])->toArray()
            );

            $groupIndex++;
        }
    }
}
