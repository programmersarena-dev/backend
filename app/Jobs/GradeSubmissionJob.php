<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Submission;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class GradeSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $submissionId;
    protected $languageKey;
    protected $version;
    protected $timeLimit;
    protected $memoryLimit;

    public function __construct($submissionId, $languageKey, $version, $timeLimit = 1, $memoryLimit = 256)
    {
        $this->submissionId = $submissionId;
        $this->languageKey = $languageKey;
        $this->version = $version;
        $this->timeLimit = $timeLimit;
        $this->memoryLimit = $memoryLimit;
    }

    public function handle()
    {
        // Eager load problem and contest relationships safely
        $submission = Submission::with('problem.contest')->find($this->submissionId);
        if (!$submission) {
            return;
        }

        $submission->update(['status' => 'Judging']);

        $problem = $submission->problem;
        $contest = $problem->contest;
        $isIOI = $contest && optional($contest->type)->name === 'IOI';

        // Fix: Defensive stripping of double-serialized wrapping quotes from the DB code string
        $sourceCode = $submission->code;
        if (str_starts_with($sourceCode, '"') && str_ends_with($sourceCode, '"')) {
            $decoded = json_decode($sourceCode);
            if (json_last_error() === JSON_ERROR_NONE) {
                $sourceCode = $decoded;
            }
        }

        // Get extension configuration
        $extension = config("languages.dockerLanguages.{$this->languageKey}.extension", 'txt');

        // Base job structure
        $job = [
            'id' => "sub-{$submission->id}-" . uniqid(),
            'language' => $this->languageKey,
            'version' => $this->version,
            'files' => [
                "submission.{$extension}" => $sourceCode,
                "grader.{$extension}" => $problem->grader_code ?? '',
            ],
            'time_limit' => (int) ($this->timeLimit ?? $problem->time_limit ?? 1),
            'memory_limit' => (int) ($this->memoryLimit ?? $problem->memory_limit ?? 256),
            'grading_type' => $isIOI ? 'IOI' : 'Standard',
            'subtasks' => []
        ];

        $problemFolder = storage_path('app/public/' . $problem->test_cases);

        if ($isIOI) {
            // IOI Logic: Process test cases grouped inside subtask folders using points.json maps
            $pointsFile = "{$problemFolder}/points.json";
            $points = file_exists($pointsFile) ? json_decode(file_get_contents($pointsFile), true) : [];

            foreach ($points as $index => $pointValue) {
                $testCases = glob("{$problemFolder}/tests/{$index}*.in");
                natsort($testCases);

                $tests = [];
                foreach ($testCases as $testCaseFile) {
                    $expectedOutputFile = str_replace('.in', '.out', $testCaseFile);
                    $tests[] = [
                        'input' => file_get_contents($testCaseFile),
                        'expected_output' => file_exists($expectedOutputFile) ? file_get_contents($expectedOutputFile) : ''
                    ];
                }

                $job['subtasks'][] = [
                    'index' => (int) $index,
                    'points' => (int) $pointValue,
                    'tests' => $tests
                ];
            }
        } else {
            // Standard Logic: Collect sequential flat files into index 0 parent wrapper
            $testCases = glob("{$problemFolder}/*.in");
            natsort($testCases);

            $tests = [];
            foreach ($testCases as $testCaseFile) {
                $expectedOutputFile = str_replace('.in', '.out', $testCaseFile);
                $tests[] = [
                    'input' => file_get_contents($testCaseFile),
                    'expected_output' => file_exists($expectedOutputFile) ? file_get_contents($expectedOutputFile) : ''
                ];
            }

            $job['subtasks'][] = [
                'index' => 0,
                'points' => (int) ($problem->score ?? 100),
                'tests' => $tests
            ];
        }

        // Push serialized structural job details to Redis queue
        try {
            Redis::lpush('judge:jobs', json_encode($job));
        } catch (\Exception $e) {
            Log::error("Redis LPUSH failed for submission {$submission->id}: " . $e->getMessage());
            $submission->update(['status' => 'Queue Error']);
        }
    }
}
