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
            'files' => [
                "submission.{$extension}" => $sourceCode,
                "grader.{$extension}" => $problem->grader_code ?? '',
            ],
            'time_limit' => (int) ($this->timeLimit ?? $problem->time_limit ?? 1),
            'memory_limit' => (int) ($this->memoryLimit ?? $problem->memory_limit ?? 256),
            'grading_type' => $isIOI ? 'IOI' : 'Standard',
            'subtasks' => []
        ];

        if ($isIOI) {
            $pointsFile = "{$problemFolder}/points.json";
            $points = file_exists($pointsFile) ? json_decode(file_get_contents($pointsFile), true) : [];

            foreach ($points as $index => $pointValue) {
                $testCases = glob("{$problemFolder}/tests/{$index}_*.in");
                natsort($testCases);

                $tests = [];
                foreach ($testCases as $testCaseFile) {
                    $expectedOutputFile = str_replace('.in', '.out', $testCaseFile);

                    // Compute relative path within the zip archive (e.g. "tests/0_1.in")
                    $inputFileRelative = ltrim(str_replace($problemFolder, '', $testCaseFile), '/\\');
                    $outputFileRelative = ltrim(str_replace($problemFolder, '', $expectedOutputFile), '/\\');

                    $tests[] = [
                        'input_file' => $inputFileRelative,
                        'expected_output_file' => $outputFileRelative,
                    ];
                }

                $job['subtasks'][] = [
                    'index' => (int) $index,
                    'points' => (int) $pointValue,
                    'tests' => $tests
                ];
            }
        } else {
            $testCases = glob("{$problemFolder}/*.in");
            natsort($testCases);

            $tests = [];
            foreach ($testCases as $testCaseFile) {
                $expectedOutputFile = str_replace('.in', '.out', $testCaseFile);

                // Compute relative path within the zip archive (e.g. "1.in")
                $inputFileRelative = ltrim(str_replace($problemFolder, '', $testCaseFile), '/\\');
                $outputFileRelative = ltrim(str_replace($problemFolder, '', $expectedOutputFile), '/\\');

                $tests[] = [
                    'input_file' => $inputFileRelative,
                    'expected_output_file' => $outputFileRelative,
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