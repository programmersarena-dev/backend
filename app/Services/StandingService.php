<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\ContestStanding;
use Illuminate\Support\Facades\Log;

class StandingService
{
    public function updateContestStandings(Submission $submission, mixed $subtasksData): void
    {
        try {
            $contest = $submission->contest;
            if (!$contest) {
                return;
            }

            if (is_string($subtasksData)) {
                $subtasksData = json_decode($subtasksData, true) ?? [];
            } elseif (!is_array($subtasksData)) {
                $subtasksData = [];
            }

            $userHandle = $submission->user->handle ?? null;
            if (!$userHandle) {
                return;
            }

            $standing = ContestStanding::firstOrCreate(
                ['contest_id' => $contest->id],
                ['result' => []]
            );

            try {
                $standing->addUserStanding($userHandle);
            } catch (\Throwable $e) {
                return;
            }

            $standing->refresh();

            $problemIds = $contest->problems()->pluck('problems.id')->map(fn($id) => (int)$id)->toArray();
            $submissionProblemId = (int) $submission->problem_id;
            
            $problemIndex = array_search($submissionProblemId, $problemIds, true);

            if ($problemIndex === false) {
                return;
            }

            $results = is_string($standing->result)
                ? (json_decode($standing->result, true) ?? [])
                : ($standing->result ?? []);

            $userMatched = false;

            foreach ($results as &$entry) {
                $entryUser = $entry['handle'] ?? null;
                
                if (is_array($entryUser)) {
                    $entryUser = $entryUser[0] ?? null;
                }

                if ((string)$entryUser === (string)$userHandle) {
                    $userMatched = true;

                    if ($contest->hasSubtasks()) {
                        if (!isset($entry['problems'][$problemIndex]) || !is_array($entry['problems'][$problemIndex])) {
                            $entry['problems'][$problemIndex] = [];
                        }

                        foreach ($subtasksData as $sIdx => $subtask) {
                            $earnedPoints = (int) ($subtask['points'] ?? 0);
                            $currentPoints = (int) ($entry['problems'][$problemIndex][$sIdx] ?? 0);
                            $entry['problems'][$problemIndex][$sIdx] = max($currentPoints, $earnedPoints);
                        }
                    } else {
                        $isAccepted = in_array($submission->status, ['OK', 'AC', '100', 'Accepted']);
                        $score = $isAccepted ? 100 : 0;
                        $currentScore = (int) ($entry['problems'][$problemIndex]['score'] ?? 0);

                        $entry['problems'][$problemIndex] = [
                            'score' => max($currentScore, $score),
                            'status' => $submission->status,
                        ];
                    }

                    $entry['total_score'] = $this->calculateTotalScore($entry['problems']);
                    break;
                }
            }
            unset($entry);

            if (!$userMatched) {
                return;
            }

            $standing->sortResult($results);

        } catch (\Throwable $e) {
            Log::error("Failed to update standings for submission #{$submission->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function calculateTotalScore(array $problems): int
    {
        $total = 0;
        foreach ($problems as $prob) {
            if (is_array($prob)) {
                if (isset($prob['score'])) {
                    $total += (int) $prob['score'];
                } else {
                    foreach ($prob as $subScore) {
                        $total += is_numeric($subScore) ? (int) $subScore : 0;
                    }
                }
            }
        }
        return $total;
    }
}