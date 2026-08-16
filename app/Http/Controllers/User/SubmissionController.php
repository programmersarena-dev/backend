<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;


use App\Models\Contest;
use App\Models\Problem;
use App\Models\Submission;

use App\Jobs\GradeSubmissionJob;

use App\Http\Requests\StoreSubmissionRequest;

use App\Http\Resources\User\Submission\SubmissionListResource;
use App\Http\Resources\User\Submission\SubmissionDetailResource;

class SubmissionController extends Controller
{
    /**
     * Fetch paginated list of all submissions.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        if ($request->has('contest_id') && $request->has('char')) {
            $contest = Contest::find($request->contest_id);
            if (!$contest) {
                return response()->json(['message' => __('messages.contest_not_found')], 404);
            }
            $problem = $contest->getProblemByCharacter($request->char);
            if (!$problem) {
                return response()->json(['message' => __('messages.problem_not_found')], 404);
            }
            $submissions = Submission::where('problem_id', $problem->id)
                ->with(['user', 'problem'])
                ->orderBy('id', 'desc')
                ->paginate(100);

            return SubmissionListResource::collection($submissions);
        }

        return SubmissionListResource::collection(
            Submission::query()
                ->with(['user', 'problem'])
                ->orderBy('id', 'desc')
                ->paginate(100)
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

        $lastSubmission = $problem->submissions()
            ->where('user_id', $userId)
            ->latest('id')
            ->first(['created_at']);

        if ($lastSubmission && $lastSubmission->created_at->diffInSeconds(now()) < 30) {
            return response()->json(['message' => __('messages.submission_rate_limit')], 422);
        }

        $codeContent = $request->input('code');
        if (!$codeContent && $request->hasFile('file')) {
            $codeContent = file_get_contents($request->file('file')->getRealPath());
        }

        $languageWithVersion = $request->input('language');
        [$languageKey, $version] = array_pad(explode('-', $languageWithVersion, 2), 2, '');

        $submission = Submission::create([
            'user_id'    => $userId,
            'problem_id' => $problem->id,
            'contest_id' => $problem->contest_id,
            'language'   => $languageWithVersion,
            'code'       => $codeContent,
            'status'     => 'Queued',
        ]);

        GradeSubmissionJob::dispatch(
            $submission->id,
            $languageKey,
            $version,
            $problem->time_limit ?? 1,
            $problem->memory_limit ?? 256
        );

        return response()->json(['message' => __('messages.submission_success')], 201);
    }

    public function status(Submission $submission): JsonResponse
    {
        if (!$submission) {
            return response()->json(['message' => __('messages.submission_not_found')], 404);
        }
 
        // Already resolved — no reason to touch Redis for a submission
        // that's already Accepted/WA/CE/etc.
        if (!$submission->isPending()) {
            return response()->json([
                'status' => $submission->status,
                'time' => $submission->time ?? 0,
                'memory' => $submission->memory ?? 0,
            ]);
        }

        $redisData = $this->getSubmissionDataFromRedis($submission);
        $liveStatus = $redisData['status'] ?? $this->resolveLiveStatusFromRedis($submission);
 
        return response()->json([
            'status' => $liveStatus ?? $submission->status,
            'time' => $redisData['time'] ?? $submission->time ?? 0,
            'memory' => $redisData['memory'] ?? $submission->memory ?? 0,
            'test' => $redisData['test'] ?? null,
            'tests' => $redisData['tests'] ?? [],
        ]);
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
                if (!empty($hash)) {
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

        $status = $this->getStatus($data['status'],$data['test']) ?? null;
        $time = isset($data['time']) ? (int) $data['time'] : (isset($data['max_time']) ? (int) $data['max_time'] : null);
        $memory = isset($data['memory']) ? (int) $data['memory'] : (isset($data['max_memory']) ? (int) $data['max_memory'] : null);
        $test = isset($data['test']) ? (int) $data['test'] : null;

        $tests = [];
        if (!empty($data['tests'])) {
            $tests = is_string($data['tests']) ? json_decode($data['tests'], true) : $data['tests'];
        } elseif (!empty($data['subtasks'])) {
            $tests = is_string($data['subtasks']) ? json_decode($data['subtasks'], true) : $data['subtasks'];
        }

        return [
            'status' => $status,
            'time' => $time,
            'memory' => $memory,
            'test' => $test,
            'tests' => $tests,
        ];
    }

    private function getStatus($status, $test)
    {
        if($status === "OK")return "Judging-#".($test+1);
        if($status !== "OK" && $status !== "AC")return $status."-#".($test);
        return $status;
    }
 
    /**
     * Checks the daemon's own in-flight Redis state for a more accurate
     * status than the DB column can give while a submission is pending —
     * the DB only gets updated once ListenJudgeResults processes the
     * final result, so a submission that's actively being judged right
     * now can look identical to one that's still sitting untouched in
     * the queue if you only look at the `status` column.
     */
    private function resolveLiveStatusFromRedis(Submission $submission): ?string
    {
        $jobPrefix = "sub-{$submission->id}-";
 
        // Currently being processed by some worker right now?
        // judge:jobs:processing_at is bounded by "jobs in flight across
        // the whole fleet right now" (small — at most one per active
        // worker), so a full HGETALL here is cheap and avoids relying on
        // HSCAN's exact return shape, which differs subtly between the
        // phpredis and predis drivers.
        $processing = Redis::hgetall('judge:jobs:processing_at');
        foreach (array_keys($processing) as $jobId) {
            if (str_starts_with($jobId, $jobPrefix)) {
                return Submission::STATUS_JUDGING;
            }
        }
 
        // Still sitting in the main queue, not yet picked up by any
        // worker? judge:jobs can be much deeper than processing_at
        // during a submission rush, so this is an O(queue depth) scan —
        // fine for occasional per-submission status checks, but if
        // queue depth regularly gets into the thousands this is worth
        // replacing with a side-index (e.g. a small
        // judge:jobs:by-submission:{id} key set at enqueue time in
        // GradeSubmissionJob and cleared once a worker picks the job
        // up) for an O(1) lookup instead.
        $queued = Redis::lrange('judge:jobs', 0, -1);
        foreach ($queued as $raw) {
            $job = json_decode($raw, true);
            if (($job['id'] ?? null) && str_starts_with($job['id'], $jobPrefix)) {
                return Submission::STATUS_IN_QUEUE;
            }
        }
 
        // Not found in either place — either it hasn't been enqueued
        // yet, or it already finished and the DB status (returned by
        // the caller) is authoritative.
        return null;
    }
}
