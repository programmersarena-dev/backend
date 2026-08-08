<?php
// app/Console/Commands/ListenJudgeResults.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Models\Submission;

class ListenJudgeResults extends Command
{
    protected $signature = 'judge:listen-results';
    protected $description = 'Consume graded results pushed by the judge daemon';

    public function handle()
    {
        $this->info('Listening on judge:results...');

        while (true) {
            $payload = Redis::blpop('judge:results', 0); // blocks until an item arrives

            if (!$payload || !isset($payload[1])) {
                continue;
            }

            $data = json_decode($payload[1], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid judge result payload: ' . $payload[1]);
                continue;
            }

            $this->applyResult($data);
        }
    }

    protected function applyResult(array $data): void
    {
        $jobId = $data['id'] ?? null;
        $result = $data['result'] ?? null;

        if (!$jobId || !$result) {
            Log::warning('Malformed judge result payload', $data);
            return;
        }

        // job id format from GradeSubmissionJob: "sub-{submissionId}-{uniqid}"
        if (!preg_match('/^sub-(\d+)-/', $jobId, $m)) {
            Log::warning("Could not parse submission id from job id: {$jobId}");
            return;
        }

        $submission = Submission::find((int) $m[1]);
        if (!$submission) {
            Log::warning("Submission {$m[1]} not found for job {$jobId}");
            return;
        }

        $submission->update([
            'status'        => $result['status'] ?? 'Error',
            'time'          => $result['max_time_used_ms'] ?? null,
            'memory'        => $result['max_memory_used_kb'] ?? null,
            'error_message' => $result['error_message'] ?? null,
            'outputs'       => json_encode($result['subtasks'] ?? []),
            'judged_at'     => now(),
        ]);

        $this->info("Submission {$submission->id} updated → {$result['status']}");
    }
}
