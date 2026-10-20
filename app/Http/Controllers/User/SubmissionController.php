<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubmissionRequest;
use App\Http\Resources\User\Submission\SubmissionDetailResource;
use App\Http\Resources\User\Submission\SubmissionListResource;
use App\Jobs\GradeSubmissionJob;
use App\Models\Contest;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redis;

class SubmissionController extends Controller
{
    /**
     * Fetch paginated list of all submissions.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        if ($request->has('contest_id') && $request->has('char')) {
            $contest = Contest::find($request->contest_id);
            if (! $contest) {
                return response()->json(['message' => __('messages.contest_not_found')], 404);
            }
            Gate::authorize('view', $contest);
            $problem = $contest->getProblemByCharacter($request->char);
            if (! $problem) {
                return response()->json(['message' => __('messages.problem_not_found')], 404);
            }
            $submissions = Submission::where('problem_id', $problem->id)
                ->visibleTo(Auth::guard('sanctum')->user())
                ->with(['user:id,handle', 'problem.translations', 'problem.contest.problems:id,contest_id'])
                ->orderBy('id', 'desc')
                ->paginate(Submission::perPage($request))->withQueryString();

            return SubmissionListResource::collection($submissions);
        }

        return SubmissionListResource::collection(
            Submission::query()
                ->visibleTo(Auth::guard('sanctum')->user())
                ->with(['user:id,handle', 'problem.translations', 'problem.contest.problems:id,contest_id'])
                ->orderBy('id', 'desc')
                ->paginate(Submission::perPage($request))->withQueryString()
        );
    }

    /**
     * Fetch single submission layout with structural anti-cheat evaluation.
     */
    public function show(Submission $submission)
    {
        $submission->load(['user', 'problem.contest']);

        return new SubmissionDetailResource($submission);
    }

    /**
     * Store and process a user source code transaction.
     */
    public function store(StoreSubmissionRequest $request, string $code): JsonResponse
    {
        $problem = $request->problem();
        $userId = Auth::id();

        $codeContent = $request->input('code');
        if (! $codeContent && $request->hasFile('file')) {
            $codeContent = file_get_contents($request->file('file')->getRealPath());
        }

        $languageWithVersion = $request->input('language');
        [$languageKey, $version] = array_pad(explode('-', $languageWithVersion, 2), 2, '');

        $submission = DB::transaction(function () use ($userId, $problem, $languageWithVersion, $codeContent) {
            // Serialize submissions from this user, including the very first one.
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $recent = $problem->submissions()->where('user_id', $userId)
                ->where('created_at', '>', now()->subSeconds(30))->exists();
            if ($recent) {
                return null;
            }

            return Submission::create([
                'user_id' => $userId,
                'problem_id' => $problem->id,
                'contest_id' => $problem->contest_id,
                'language' => $languageWithVersion,
                'code' => $codeContent,
                'status' => 'Queued',
            ]);
        });

        if (! $submission) {
            return response()->json(['message' => __('messages.submission_rate_limit')], 422);
        }

        GradeSubmissionJob::dispatch(
            $submission->id,
            $languageKey,
            $version,
            $problem->time_limit ?? 1000,
            $problem->memory_limit ?? 256
        );

        return response()->json(['message' => __('messages.submission_success')], 201);
    }

    public function statuses(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $submissions = Submission::with(['problem.contest', 'contest.type'])
            ->whereIn('id', $validated['ids'])->get();

        $data = $submissions->filter(fn ($submission) => Gate::allows('view', $submission))
            ->map(fn ($submission) => ['id' => $submission->id] + $this->statusData($submission))->values();

        return response()->json(['data' => $data]);
    }

    public function status(Submission $submission): JsonResponse
    {
        return response()->json($this->statusData($submission));
    }

    private function statusData(Submission $submission): array
    {
        // Already resolved — no reason to touch Redis for a submission
        // that's already Accepted/WA/CE/etc.
        if (! $submission->isPending()) {
            $failed = $submission->getFirstFailedTest();

            return [
                'status' => $submission->status,
                'time' => $submission->time ?? 0,
                'memory' => $submission->memory ?? 0,
                'test' => $failed['test'],
                'subtask' => $failed['total_subtasks'] > 1 ? $failed['subtask'] : null,
            ];
        }

        $redisData = $this->getSubmissionDataFromRedis($submission);
        $liveStatus = $redisData['status'] ?? $this->resolveLiveStatusFromRedis($submission);

        return [
            'status' => $liveStatus ?? $submission->status,
            'time' => $redisData['time'] ?? $submission->time ?? 0,
            'memory' => $redisData['memory'] ?? $submission->memory ?? 0,
            'test' => $redisData['test'] ?? null,
            'subtask' => ! empty($redisData['subtask']) && $redisData['subtask'] != '0' ? (int) $redisData['subtask'] : null,
        ];
    }

    private function getSubmissionDataFromRedis(Submission $submission): array
    {
        $keys = [
            "judge:submission:{$submission->id}",
            "judge:submission:{$submission->id}:status",
            "submission:{$submission->id}:status",
            "judge:status:{$submission->id}",
        ];

        $data = [];
        foreach ($keys as $key) {
            try {
                $hash = Redis::hgetall($key);
                if (! empty($hash)) {
                    $data = $hash;
                    break;
                }
            } catch (\Throwable $e) {
                // Ignore Redis errors
            }
        }

        if (empty($data)) {
            return [];
        }

        $time = isset($data['time']) ? (int) $data['time'] : (isset($data['max_time']) ? (int) $data['max_time'] : null);
        $memory = isset($data['memory']) ? (int) $data['memory'] : (isset($data['max_memory']) ? (int) $data['max_memory'] : null);
        $test = isset($data['test']) ? (int) $data['test'] : null;

        $tests = [];
        if (! empty($data['tests'])) {
            $tests = is_string($data['tests']) ? json_decode($data['tests'], true) : $data['tests'];
        } elseif (! empty($data['subtasks'])) {
            $tests = is_string($data['subtasks']) ? json_decode($data['subtasks'], true) : $data['subtasks'];
        }

        $rawStatus = $data['status'] ?? null;
        $subtask = ! empty($data['subtask']) && $data['subtask'] != '0' ? (int) $data['subtask'] : null;

        $contestType = $submission->contest?->type?->name ?? 'Classic';

        if (isset($data['finished']) && $data['finished'] !== '1') {
            $status = in_array($rawStatus, ['In Queue', 'Queued', 'Compiling'])
                ? $rawStatus : 'Judging';
        } elseif ($contestType === 'IOI') {
            $status = is_numeric($rawStatus) ? (int) $rawStatus : $rawStatus;
        } else {
            if (is_numeric($rawStatus)) {
                $status = (int) $rawStatus;
            } elseif ($rawStatus === 'OK') {
                $status = $test && $test > 0 ? 'Judging-#'.($test + 1) : 'Judging';
            } elseif ($rawStatus !== 'AC') {
                $status = $test && $test > 0 ? $rawStatus.'-#'.$test : $rawStatus;
            } else {
                $status = $rawStatus;
            }
        }

        return [
            'status' => $status,
            'time' => $time,
            'memory' => $memory,
            'test' => $test,
            'tests' => $tests,
            'subtask' => $subtask,
        ];
    }

    private function resolveLiveStatusFromRedis(Submission $submission): ?string
    {
        try {
            return Redis::get("judge:submission:{$submission->id}:queue-state");
        } catch (\Throwable $e) {
            return null;
        }
    }
}
