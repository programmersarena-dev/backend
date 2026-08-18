<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

use App\Models\Contest;
use App\Models\Submission;
use App\Models\Problem;

use App\Jobs\GradeSubmissionJob;

class SubmissionController extends Controller
{
    public function recheckAllSubmssionsInContest(Contest $contest)
    {
        $submissions = Submission::where('contest_id', $contest->id)->get();
        $standings = $contest->standings;
        $standings->result = [];
        $standings->save();
        foreach ($submissions as $submission) {
            $this->recheck($submission);
        }
        return response()->json([
            "message" => "All submissions have been re-graded",
        ]);
    }

    public function recheckAllSubmssionsInProblem(Contest $contest, $char)
    {
        $problem = $contest->getProblemByCharacter($char);
        if (!$problem) {
            return response()->json([
                "message" => "Problem not found",
            ], 404);
        }
        $submissions = Submission::where('problem_id', $problem->id)->get();
        foreach ($submissions as $submission) {
            $this->recheck($submission);
        }
        return response()->json([
            "message" => "All submissions have been re-graded",
        ]);
    }

    /**
     * Get real-time judging status, time, and memory from Redis for a submission.
     */
    public function status(Request $request, $submission): JsonResponse
    {
        if (!($submission instanceof Submission)) {
            $submission = Submission::find($submission);
        }

        if (!$submission) {
            return response()->json([
                'message' => 'Submission not found',
            ], 404);
        }

        // Fetch test-level status, time, and memory from Redis
        $redisData = $this->getSubmissionDataFromRedis($submission);

        $status = $redisData['status'] ?? null;
        $time = $redisData['time'] ?? null;
        $memory = $redisData['memory'] ?? null;
        $test = $redisData['test'] ?? null;
        $tests = $redisData['tests'] ?? null;
        $fromRedis = !empty($redisData);

        // If Redis doesn't have live test data, check if it's currently in queue / processing
        if (empty($status) && $submission->isPending()) {
            $status = $this->resolveLiveStatusFromRedis($submission);
        }

        // Fallback to database values if not available in Redis or if already finalized
        if (empty($status)) {
            $status = $submission->status;
        }
        if ($time === null) {
            $time = $submission->time ?? 0;
        }
        if ($memory === null) {
            $memory = $submission->memory ?? 0;
        }
        if (empty($tests) && !empty($submission->outputs)) {
            $tests = is_string($submission->outputs) ? json_decode($submission->outputs, true) : $submission->outputs;
        }

        return response()->json([
            'submission_id' => $submission->id,
            'status' => $status,
            'time' => (int) $time,
            'memory' => (int) $memory,
            'test' => $test !== null ? (int) $test : null,
            'tests' => $tests ?? [],
            'subtask' => !empty($redisData['subtask']) && $redisData['subtask'] != "0" ? (int) $redisData['subtask'] : null,
            'from_redis' => $fromRedis,
            'data' => [
                'submission_id' => $submission->id,
                'status' => $status,
                'time' => (int) $time,
                'memory' => (int) $memory,
                'test' => $test !== null ? (int) $test : null,
                'tests' => $tests ?? [],
                'subtask' => !empty($redisData['subtask']) && $redisData['subtask'] != "0" ? (int) $redisData['subtask'] : null,
            ]
        ]);
    }

    /**
     * Retrieve live test progress (status, time, memory, tests) from Redis.
     */
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
                // Ignore Redis errors and continue
            }
        }

        if (empty($data)) {
            return [];
        }

        $rawStatus = $data['status'] ?? null;
        $time = isset($data['time']) ? (int) $data['time'] : (isset($data['max_time']) ? (int) $data['max_time'] : null);
        $memory = isset($data['memory']) ? (int) $data['memory'] : (isset($data['max_memory']) ? (int) $data['max_memory'] : null);
        $test = isset($data['test']) ? (int) $data['test'] : null;

        $tests = [];
        if (!empty($data['tests'])) {
            $tests = is_string($data['tests']) ? json_decode($data['tests'], true) : $data['tests'];
        } elseif (!empty($data['subtasks'])) {
            $tests = is_string($data['subtasks']) ? json_decode($data['subtasks'], true) : $data['subtasks'];
        } else {
            try {
                $rawTests = Redis::lrange("judge:submission:{$submission->id}:tests", 0, -1);
                if (!empty($rawTests)) {
                    $tests = array_map(fn($item) => json_decode($item, true) ?? $item, $rawTests);
                }
            } catch (\Throwable $e) {
                // Ignore Redis errors
            }
        }

        $subtask = !empty($data['subtask']) && $data['subtask'] != "0" ? (int) $data['subtask'] : null;

        $contestType = $submission->contest?->type?->name ?? 'Classic';

        if ($contestType === 'IOI') {
            $status = is_numeric($rawStatus) ? (int) $rawStatus : $rawStatus;
        } else {
            if (is_numeric($rawStatus)) {
                $status = (int) $rawStatus;
            } elseif ($rawStatus === "OK") {
                $status = $test && $test > 0 ? "Judging-#" . ($test + 1) : "Judging";
            } elseif ($rawStatus !== "AC") {
                $status = $test && $test > 0 ? $rawStatus . "-#" . $test : $rawStatus;
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

    /**
     * Checks in-flight or in-queue status from Redis.
     */
    private function resolveLiveStatusFromRedis(Submission $submission): ?string
    {
        $jobPrefix = "sub-{$submission->id}-";

        try {
            $processing = Redis::hgetall('judge:jobs:processing_at');
            foreach (array_keys($processing) as $jobId) {
                if (str_starts_with($jobId, $jobPrefix)) {
                    return Submission::STATUS_JUDGING;
                }
            }

            $queued = Redis::lrange('judge:jobs', 0, -1);
            foreach ($queued as $raw) {
                $job = json_decode($raw, true);
                if (($job['id'] ?? null) && str_starts_with($job['id'], $jobPrefix)) {
                    return Submission::STATUS_IN_QUEUE;
                }
            }
        } catch (\Throwable $e) {
            // Ignore Redis errors
        }

        return null;
    }

    private function recheck(Submission $submission) {
        $submission->status = 'Queued';
        $submission->outputs = null;
        $submission->time = null;
        $submission->memory = null;
        $submission->error_message = null;
        $submission->judged_at = null;
        $submission->save();

        [$language, $version] = explode('-', $submission->language);
        GradeSubmissionJob::dispatch(
            $submission->id,
            $language,
            $version,
            $submission->problem->time_limit,
            $submission->problem->memory_limit
        );
    }
}
