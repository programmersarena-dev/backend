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
        $submission = Submission::with('problem.contest')->find($this->submissionId);
        if (!$submission) {
            return;
        }

        $submission->update(['status' => 'Judging']);

        $problem = $submission->problem;
        $contest = $problem->contest;
        $isIOI = $contest && optional($contest->type)->name === 'IOI';

        $sourceCode = $submission->code;
        if (str_starts_with($sourceCode, '"') && str_ends_with($sourceCode, '"')) {
            $decoded = json_decode($sourceCode);
            if (json_last_error() === JSON_ERROR_NONE) {
                $sourceCode = $decoded;
            }
        }

        $extension = config("languages.dockerLanguages.{$this->languageKey}.extension", 'txt');

        $problemFolder = storage_path('app/public/' . $problem->test_cases_path);

        // Compute a fingerprint of all test files so judge nodes automatically invalidate disk cache when test files change
        $fingerprintParts = [$problem->test_cases_path, (string) ($problem->updated_at ?? '')];
        if (is_dir($problemFolder)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($problemFolder, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $fingerprintParts[] = $file->getFilename() . ':' . $file->getMTime() . ':' . $file->getSize();
                }
            }
        }
        $testCasesVersion = $problem->test_cases_version ?? md5(implode('|', $fingerprintParts));

        $job = [
            'id' => "sub-{$submission->id}-" . uniqid(),
            'submission_id' => $submission->id,
            'problem_id' => $problem->id,
            'test_cases_version' => $testCasesVersion,
            'language' => $this->languageKey,
            'version' => $this->version,
            'main_file' => "submission.{$extension}",
            'files' => [
                "submission.{$extension}" => $sourceCode,
                "checker.cpp" => $problem->checker_code ?? '',
            ],
            'time_limit' => (int) ($this->timeLimit ?? $problem->time_limit ?? 1),
            'memory_limit' => (int) ($this->memoryLimit ?? $problem->memory_limit ?? 256),
            'grading_type' => $contest->type()?->name ?? 'Classic',
            'subtasks' => []
        ];

        if ($isIOI) {
            $subtaskFiles = glob("{$problemFolder}/subtasks/*.json");
            natsort($subtaskFiles);
 
            foreach (array_values($subtaskFiles) as $subtaskIndex => $subtaskFile) {
                $subtaskMeta = json_decode(file_get_contents($subtaskFile), true) ?? [];
                $testNames = $subtaskMeta['testcases'] ?? [];
 
                $tests = [];
                foreach ($testNames as $testName) {
                    $inputFile = "{$problemFolder}/tests/{$testName}.in";
                    $outputFile = "{$problemFolder}/tests/{$testName}.out";
                    $tests[] = [
                        'input' => file_exists($inputFile) ? file_get_contents($inputFile) : '',
                        'expected_output' => file_exists($outputFile) ? file_get_contents($outputFile) : '',
                    ];
                }
 
                $job['subtasks'][] = [
                    'index' => $subtaskIndex,
                    'points' => (int) ($subtaskMeta['score'] ?? 0),
                    'tests' => $tests,
                ];
            }
        } else {
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

        try {
            Redis::lpush('judge:jobs', json_encode($job));
        } catch (\Exception $e) {
            Log::error("Redis LPUSH failed for submission {$submission->id}: " . $e->getMessage());
            $submission->update(['status' => 'Queue Error']);
        }
    }
}