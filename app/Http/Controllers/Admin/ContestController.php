<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContestRequest;
use App\Http\Requests\UpdateContestRequest;
use App\Http\Resources\ContestDetailResource;
use App\Http\Resources\ContestListResource;
use App\Models\Contest;
use App\Models\ContestType;
use App\Models\Standing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ContestNotification;

class ContestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $now = Carbon::now('UTC');

        $contests = Contest::orderBy('start_date', 'desc')->get();

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

        $authorIds = collect($data['authors'])->map(function ($userName) use ($users) {
            return $users[$userName]->id ?? 0;
        });

        $participantIds = $this->getParticipantIds($data['participants'], $data['type']);

        $contest = Contest::create([
            'type_id' => $type->id,
            'name' => $data['name'],
            'authorIds' => json_encode($authorIds),
            'start_date' => Carbon::parse($data['start_date'])->setTimezone('UTC')->toIso8601String(),
            'duration' => $data['duration'],
            'participantIds' => json_encode($participantIds),
            'official' => $data['official'],
            'active' => $data['active'],
        ]);

        Standing::create([
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

        $authorIds = collect($data['authors'])->map(function ($userName) use ($users) {
            return $users[$userName]->id ?? 0;
        });

        $participantIds = $this->getParticipantIds($data['participants'], $data['type']);

        $contest->update([
            'type_id' => $type->id,
            'name' => $data['name'],
            'authorIds' => json_encode($authorIds),
            'start_date' => Carbon::parse($data['start_date'])->setTimezone('UTC')->toIso8601String(),
            'duration' => $data['duration'],
            'participantIds' => json_encode($participantIds),
            'official' => $data['official'],
            'active' => $data['active'],
        ]);

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

    private function getParticipantIds($participants, $type)
    {
        $users = User::all()->keyBy('name');

        $isDuel = $type === 'Duel';

        $mapFn = function ($entry) use ($users, $isDuel) {
            if ($isDuel) {
                return [
                    $users[$entry[0]]->id ?? 0,
                    $users[$entry[1]]->id ?? 0
                ];
            }
            return $users[$entry]->id ?? 0;
        };

        $officialParticipantIds = collect($participants['official'])->map($mapFn);
        $unofficialParticipantIds = collect($participants['unofficial'])->map($mapFn);

        $participantIds = [
            'official' => $officialParticipantIds,
            'unofficial' => $unofficialParticipantIds,
        ];

        return $participantIds;
    }
}
