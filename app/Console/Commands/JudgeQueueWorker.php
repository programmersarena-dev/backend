<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\Submission;
use App\Models\Contest;

class JudgeQueueWorker extends Command
{
    protected $signature = 'judge:worker';
    protected $description = 'Listen to judge results queue and update submissions';

    public function handle()
    {
        $this->info('Starting judge result consumer...');

        while (true) {
            // BRPOP blocks until a result is available
            $payload = Redis::brpop('judge:results', 0);

            if (!$payload) {
                continue;
            }

            // $payload is [key, value]
            $raw = $payload[1];

            try {
                $data = json_decode($raw, true);
                if (!isset($data['id']) || !isset($data['result'])) {
                    $this->error('Invalid result payload: ' . $raw);
                    continue;
                }

                // Extract submission ID from job ID (format: sub-{id}-{uniqid})
                preg_match('/sub-(\d+)-/', $data['id'], $m);
                if (!isset($m[1])) {
                    $this->warn('Could not extract submission ID from job ID: ' . $data['id']);
                    continue;
                }

                $submissionId = (int)$m[1];
                $result = $data['result'];

                $submission = Submission::find($submissionId);
                if (!$submission) {
                    $this->warn("Submission {$submissionId} not found");
                    continue;
                }

                // Map judge verdict to submission status
                $verdictMap = [
                    'OK' => 'Accepted',
                    'AC' => 'Accepted',
                    'WA' => 'Wrong Answer',
                    'CE' => 'Compilation Error',
                    'TLE' => 'Time Limit Exceeded',
                    'MLE' => 'Memory Limit Exceeded',
                    'RE' => 'Runtime Error',
                ];

                $submission->status = $verdictMap[$result['status']] ?? 'Judging Error';
                $submission->time = $result['time_used_ms'] ?? 0;
                $submission->memory = $result['memory_used_kb'] ?? 0;
                $submission->output = $result['raw_output'] ?? '';
                $submission->error_message = $result['error_message'];
                $submission->judged_at = now();
                $submission->save();

                // Update contest standings if part of a contest
                if ($submission->problem && $submission->problem->contest_id) {
                    $this->updateContestStandings($submission);
                }

                $this->info("Judged submission {$submissionId}: {$submission->status}");
            } catch (\Exception $e) {
                $this->error('Error processing result: ' . $e->getMessage());
            }
        }
    }

    protected function updateContestStandings($submission)
    {
        // Recalculate standings for this contest
        $contest = $submission->problem->contest;

        // Get all AC submissions by this user for this contest
        $acCount = Submission::whereHas('problem', function ($q) use ($contest) {
            $q->where('contest_id', $contest->id);
        })
            ->where('user_id', $submission->user_id)
            ->where('status', 'Accepted')
            ->distinct('problem_id')
            ->count();

        // Update or create standing
        $standing = \App\Models\Standing::firstOrCreate([
            'contest_id' => $contest->id,
            'user_id' => $submission->user_id,
        ]);

        $standing->solved = $acCount;
        $standing->save();
    }
}
