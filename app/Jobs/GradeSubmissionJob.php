<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Submission;
use Illuminate\Support\Facades\Redis;
use App\Services\CodeService;

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
        $submission = Submission::find($this->submissionId);
        if (!$submission) {
            return;
        }

        $submission->status = 'Judging';
        $submission->save();

        // Build judge-box job
        $job = [
            'id' => "sub-{$submission->id}-" . uniqid(),
            'language' => $this->languageKey,
            'version' => $this->version,
            'files' => [
                'submission.' . config("languages.dockerLanguages.{$this->languageKey}.extension") => $submission->code,
                // if grader exists
                'grader.' . config("languages.dockerLanguages.{$this->languageKey}.extension") => $submission->problem->grader_code ?? '',
            ],
            'input' => $submission->test_input,
            'expected_output' => $submission->test_output,
            'time_limit' => $this->timeLimit,
            'memory_limit' => $this->memoryLimit,
        ];

        // Push to judge:jobs queue
        Redis::lpush('judge:jobs', json_encode($job));
    }
}
