<?php

namespace App\Console\Commands;

use App\Models\Standing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\Submission;
use App\Models\Contest;
use Illuminate\Support\Facades\Log;

class JudgeQueueWorker extends Command
{
    protected $signature = 'judge:worker';
    protected $description = 'Listen to judge results queue and calculate IOI/Standard scores';

    public function handle()
    {
        $this->info('Starting judge result consumer...');

        while (true) {
            try {
                // Using Redis connection safely
                $payload = Redis::connection()->client()->brpop('judge:results', 5);
                if (!$payload)
                    continue;

                $data = json_decode($payload[1], true);
                if (!isset($data['id']) || !isset($data['result']))
                    continue;

                preg_match('/sub-(\d+)-/', $data['id'], $m);
                $submissionId = (int) ($m[1] ?? 0);

                // Eager load relations defensively to ensure everything is initialized for calculations
                $submission = Submission::with(['problem.contest.type', 'user'])->find($submissionId);
                if (!$submission)
                    continue;

                $result = $data['result'];

                // If it failed to compile entirely, handle it cleanly right away
                if (($result['status'] ?? '') === 'CE') {
                    $this->updateSubmissionVerdict($submission, 'Compilation Error', 0, $result, [], []);
                    continue;
                }

                $totalScore = 0;
                $allClear = true;
                $firstError = null;

                $subtaskResults = $result['subtasks'] ?? [];
                $outputsToSave = [];
                $countPoints = []; // FIX: Initializing array container to map subtask totals

                foreach ($subtaskResults as $subtask) {
                    $subtaskPassed = true;
                    $subtaskIndex = $subtask['index'] ?? 0;

                    foreach ($subtask['tests'] as $testResult) {
                        if (($testResult['status'] ?? '') !== 'OK' && ($testResult['status'] ?? '') !== 'AC') {
                            $subtaskPassed = false;
                            $allClear = false;
                            if (!$firstError) {
                                $firstError = $testResult['status'];
                            }
                        }
                    }

                    $subtaskValue = intval($subtask['points'] ?? 0);
                    $earnedPoints = $subtaskPassed ? $subtaskValue : 0;

                    if ($subtaskPassed) {
                        $totalScore += $subtaskValue;
                    }

                    // Map specific subtask index point values for the standings engine
                    $countPoints[$subtaskIndex] = $earnedPoints;

                    $outputsToSave[] = [
                        'subTaskResults' => $subtask['tests'],
                        'point' => $earnedPoints,
                    ];
                }

                // Determine context-aware text labels
                $finalVerdict = $allClear ? 'Accepted' : (is_numeric($totalScore) && $totalScore > 0 ? "Partial ($totalScore)" : ($firstError ?? 'Wrong Answer'));

                // Safe fallback evaluation pattern checking if problem has contest definitions
                $contest = $submission->problem->contest ?? null;
                $contestTypeName = $contest && optional($contest->type)->name ? $contest->type->name : 'Standard';

                if ($contestTypeName !== 'IOI' && !$allClear) {
                    $finalVerdict = $firstError ?? 'Wrong Answer';
                }

                // FIX: All 6 required variables are properly packed and allocated here
                $this->updateSubmissionVerdict($submission, $finalVerdict, $totalScore, $result, $outputsToSave, $countPoints);

                $this->info("Judged submission {$submissionId}: {$finalVerdict}");

            } catch (\Throwable $e) { // FIX: Changed Exception to Throwable to capture system level engine errors
                $this->error('Error processing result: ' . $e->getMessage());
                Log::error('JudgeQueueWorker Failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                sleep(1);
            }
        }
    }

    // FIX: Configured default fallback properties to insulate execution flows
    private function updateSubmissionVerdict($submission, $verdict, $score, $rawResult, $outputs = [], $countPoints = [])
    {
        $hasAlreadySolved = Submission::where('user_id', $submission->user_id)
            ->where('problem_id', $submission->problem_id)
            ->where('status', 'Accepted')
            ->where('id', '!=', $submission->id)
            ->exists();

        $submission->update([
            'status' => $verdict,
            'score' => $score,
            'time' => $rawResult['max_time_used_ms'] ?? 0,
            'memory' => $rawResult['max_memory_used_kb'] ?? 0,
            'outputs' => json_encode($outputs),
            'error_message' => $rawResult['error_message'] ?? null,
            'judged_at' => now(),
        ]);

        $contest = $submission->problem->contest ?? null;
        if ($contest) {
            if ($contest->getStatus() === 'started' && !$hasAlreadySolved) {

                $verdictParam = method_exists($contest, 'hasSubtasks') && $contest->hasSubtasks() ? $countPoints : $submission->status;
                $problemCharIdentifier = method_exists($submission->problem, 'char') ? $submission->problem->char() : 'A';

                $this->calcContestStandings(
                    $contest->id,
                    ord($problemCharIdentifier) - ord('A'),
                    $submission->problem->score ?? 100,
                    optional($submission->user)->name ?? 'Unknown',
                    $submission->created_at->diffInSeconds($contest->start_date),
                    $verdictParam
                );
            }
        }
    }

    private function calcContestStandings($contestId, $problemId, $problemScore, $userName, $diffInSeconds, $verdict)
    {
        $contest = Contest::with('type')->find($contestId);
        if (!$contest)
            return;

        $standings = Standing::firstOrCreate(['contest_id' => $contestId]);
        $contestTypeName = optional($contest->type)->name ?? 'Classic';

        if (in_array($contestTypeName, ['Classic', 'IOI', 'ICPC'])) {
            $standings->addUserStanding([$userName], $contestTypeName);
        } elseif ($contestTypeName === 'Duel') {
            $standings->addUserStanding([$userName, $contest->getComponent($userName)], $contestTypeName);
        }

        // Reload data fresh from tracking definitions
        $resultData = collect(json_decode($standings->result, true) ?? []);

        if (method_exists($contest, 'hasSubtasks') && $contest->hasSubtasks()) {
            $resultData = $this->updateSubtaskScores($resultData, $contestTypeName, $userName, $problemId, $verdict);
        } else {
            $score = ($verdict === 'Accepted') ? $problemScore - intdiv($diffInSeconds, 60) : -50;
            $resultData = $this->updateRegularScores($resultData, $contestTypeName, $userName, $problemId, $score, $diffInSeconds);
        }

        $sortedResults = $resultData->sortByDesc('total_score')->values()->toArray();
        $standings->update([
            'result' => json_encode($sortedResults),
        ]);
    }

    private function updateRegularScores($result, $contestType, $userName, $problemId, $score, $diffInSeconds)
    {
        return collect($result)->map(function ($item) use ($userName, $problemId, $score, $contestType, $diffInSeconds) {
            if ($item['username'] === $userName) {
                if ($contestType !== 'Duel' || ($contestType === 'Duel' && ($item['problems2'][$problemId]['score'] ?? 0) <= 0)) {
                    $item['problems'][$problemId]['score'] = ($item['problems'][$problemId]['score'] ?? 0) + $score;
                    if ($score > 0) {
                        $item['problems'][$problemId] = [
                            'score' => $item['problems'][$problemId]['score'],
                            'accepted_at' => gmdate('H:i:s', $diffInSeconds),
                        ];
                    }
                    $item['total_score'] = ($item['total_score'] ?? 0) + $score;
                }
            } elseif ($contestType === 'Duel' && ($item['username2'] ?? '') === $userName) {
                if ($contestType !== 'Duel' || ($contestType === 'Duel' && ($item['problems'][$problemId]['score'] ?? 0) <= 0)) {
                    $item['problems2'][$problemId]['score'] = ($item['problems2'][$problemId]['score'] ?? 0) + $score;
                    if ($score > 0) {
                        $item['problems2'][$problemId] = [
                            'score' => $item['problems2'][$problemId]['score'],
                            'accepted_at' => gmdate('H:i:s', $diffInSeconds),
                        ];
                    }
                    $item['total_score2'] = ($item['total_score2'] ?? 0) + $score;
                }
            }

            if ($contestType === 'Duel') {
                if (($item['total_score2'] ?? 0) > ($item['total_score'] ?? 0)) {
                    [$item['username'], $item['username2']] = [$item['username2'], $item['username']];
                    [$item['problems'], $item['problems2']] = [$item['problems2'], $item['problems']];
                    [$item['total_score'], $item['total_score2']] = [$item['total_score2'], $item['total_score']];
                }
            }
            return $item;
        });
    }

    private function updateSubtaskScores($result, $contestType, $userName, $problemId, $points)
    {
        $pointsArray = is_array($points) ? $points : [$points];

        return collect($result)->map(function ($item) use ($userName, $problemId, $pointsArray, $contestType) {
            if ($item['username'] === $userName) {
                if (!is_array($item['problems'][$problemId] ?? null)) {
                    $item['problems'][$problemId] = array_fill(0, count($pointsArray), 0);
                }
                if (count($item['problems'][$problemId]) < count($pointsArray)) {
                    $item['problems'][$problemId] = array_fill(0, count($pointsArray), 0);
                }
                foreach ($pointsArray as $index => $point) {
                    if ($contestType !== 'Duel' || ($contestType === 'Duel' && max($item['problems'][$problemId][$index], $point) > ($item['problems2'][$problemId][$index] ?? 0))) {
                        $item['problems'][$problemId][$index] = max($item['problems'][$problemId][$index], $point);
                    }
                }
                $item['total_score'] = $this->calculateTotalScore($item);
            } elseif ($contestType === 'Duel' && ($item['username2'] ?? '') === $userName) {
                if (!is_array($item['problems2'][$problemId] ?? null)) {
                    $item['problems2'][$problemId] = array_fill(0, count($pointsArray), 0);
                }
                if (count($item['problems2'][$problemId]) < count($pointsArray)) {
                    $item['problems2'][$problemId] = array_fill(0, count($pointsArray), 0);
                }
                foreach ($pointsArray as $index => $point) {
                    if ($contestType !== 'Duel' || ($contestType === 'Duel' && max($item['problems'][$problemId][$index], $point) > ($item['problems2'][$problemId][$index] ?? 0))) {
                        $item['problems2'][$problemId][$index] = max($item['problems2'][$problemId][$index], $point);
                    }
                }
                $item['total_score2'] = $this->calculateTotalScore($item, true);
            }
            return $item;
        });
    }

    private function calculateTotalScore($item, $useProblems2 = false)
    {
        $problemsKey = $useProblems2 ? 'problems2' : 'problems';
        return array_sum(array_map('array_sum', array_filter($item[$problemsKey] ?? [], 'is_array')));
    }
}
