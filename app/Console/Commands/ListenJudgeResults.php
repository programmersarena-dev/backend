<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Submission;
use App\Services\StandingService;

class ListenJudgeResults extends Command
{
    protected $signature = 'judge:listen-results';
    protected $description = 'Consume graded results pushed by the judge daemon and update standings';

    public function handle()
    {
        $this->info('Listening on judge:results...');

        while (true) {
            try {
                $payload = Redis::blpop('judge:results', 0);

                if (!$payload || !isset($payload[1])) {
                    continue;
                }

                $data = json_decode($payload[1], true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                $this->applyResult($data);
            } catch (\Throwable $e) {
                Log::error('Error processing judge result in worker: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    protected function applyResult(array $data): void
    {
        $jobId = $data['id'] ?? null;
        $result = $data['result'] ?? null;

        if (!$jobId || !$result) {
            return;
        }

        if (!preg_match('/^sub-(\d+)-/', $jobId, $m)) {
            return;
        }

        $submission = Submission::with(['user', 'contest'])->find((int) $m[1]);
        if (!$submission) {
            return;
        }

        $status = $result['status'] ?? 'Error';
        $subtasksData = $result['subtasks'] ?? [];

        $submission->update([
            'status' => $status,
            'time' => $result['max_time_used_ms'] ?? null,
            'memory' => $result['max_memory_used_kb'] ?? null,
            'error_message' => $result['error_message'] ?? null,
            'outputs' => is_array($subtasksData) ? json_encode($subtasksData) : $subtasksData,
            'judged_at' => now(),
        ]);

        $this->info("Submission {$submission->id} updated → {$status}");

        if (in_array($status, ['Judge Error', 'Queue Error', 'System Error'])) {
            return;
        }

        if ($submission->contest_id && $submission->contest) {
            $startDate = $submission->contest->start_date;

            $isStarted = $startDate instanceof Carbon 
                ? $startDate->isPast() 
                : ($startDate ? Carbon::parse($startDate)->isPast() : false);

            if ($isStarted) {
                app(StandingService::class)->updateContestStandings($submission, $subtasksData);
            }
        }
    }
}