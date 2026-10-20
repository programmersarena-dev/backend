<?php

namespace App\Jobs;

use App\Models\Submission;
use App\Support\TestCaseBundle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class GradeSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $submissionId;

    protected $languageKey;

    protected $version;

    protected $timeLimit;

    protected $memoryLimit;

    public function __construct($submissionId, $languageKey, $version, $timeLimit = 1000, $memoryLimit = 256)
    {
        $this->submissionId = $submissionId;
        $this->languageKey = $languageKey;
        $this->version = $version;
        $this->timeLimit = $timeLimit;
        $this->memoryLimit = $memoryLimit;
    }

    public function handle()
    {
        $submission = Submission::with('problem.contest.type')->find($this->submissionId);
        if (! $submission) {
            return;
        }

        $submission->update(['status' => Submission::STATUS_IN_QUEUE]);

        $problem = $submission->problem;
        $contest = $problem->contest;
        $isIOI = $contest && optional($contest->type)->name === 'IOI';

        $sourceCode = $submission->code;

        $extension = config("languages.dockerLanguages.{$this->languageKey}.extension", 'txt');

        $problemFolder = storage_path('app/public/'.$problem->test_cases_path);

        $testCasesVersion = TestCaseBundle::version($problem);

        $job = [
            'id' => "sub-{$submission->id}-".uniqid(),
            'submission_id' => $submission->id,
            'problem_id' => $problem->id,
            'test_cases_version' => $testCasesVersion,
            'language' => $this->languageKey,
            'version' => $this->version,
            'main_file' => "submission.{$extension}",
            'files' => [
                "submission.{$extension}" => $sourceCode,
                'checker.cpp' => $problem->checker_code ?? '',
            ],
            'time_limit' => (int) ($this->timeLimit ?? $problem->time_limit ?? 1000),
            'time_limit_unit' => 'ms',
            'memory_limit' => (int) ($this->memoryLimit ?? $problem->memory_limit ?? 256),
            'grading_type' => $contest?->type?->name ?? 'Classic',
            'subtasks' => [],
        ];

        if ($isIOI) {
            $subtaskFiles = glob("{$problemFolder}/subtasks/*.json");
            natsort($subtaskFiles);

            foreach (array_values($subtaskFiles) as $subtaskIndex => $subtaskFile) {
                $subtaskMeta = json_decode(file_get_contents($subtaskFile), true) ?? [];
                $testNames = $subtaskMeta['testcases'] ?? [];

                $tests = [];
                foreach ($testNames as $testName) {
                    $tests[] = TestCaseBundle::test('tests/', $testName);
                }

                $job['subtasks'][] = [
                    'index' => $subtaskIndex,
                    'points' => (int) ($subtaskMeta['score'] ?? 0),
                    'tests' => $tests,
                ];
            }
        } else {
            $testCases = glob("{$problemFolder}/*.in");
            $testDirectory = '';
            if (! $testCases) {
                $testCases = glob("{$problemFolder}/tests/*.in");
                $testDirectory = 'tests/';
            }
            natsort($testCases);

            $tests = [];
            foreach ($testCases as $testCaseFile) {
                $tests[] = TestCaseBundle::test($testDirectory, pathinfo($testCaseFile, PATHINFO_FILENAME));
            }

            $job['subtasks'][] = [
                'index' => 0,
                'points' => (int) ($problem->score ?? 100),
                'tests' => $tests,
            ];
        }

        try {
            Redis::transaction(function ($redis) use ($job, $submission) {
                $redis->setex("judge:submission:{$submission->id}:queue-state", 86400, 'In Queue');
                $redis->del("judge:submission:{$submission->id}", "judge:submission:{$submission->id}:status", "submission:{$submission->id}:status");
                $redis->lpush('judge:jobs', json_encode($job, JSON_THROW_ON_ERROR));
            });
        } catch (\Exception $e) {
            Log::error("Redis LPUSH failed for submission {$submission->id}: ".$e->getMessage());
            $submission->update(['status' => 'Queue Error']);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Submission::whereKey($this->submissionId)->update([
            'status' => Submission::STATUS_JUDGE_ERROR,
            'error_message' => 'Unable to prepare submission for judging.',
            'judged_at' => now(),
        ]);
        Log::error("Failed to prepare submission {$this->submissionId}", ['exception' => $exception]);
    }
}
