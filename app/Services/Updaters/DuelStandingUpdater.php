<?php

namespace App\Services\Updaters;

use App\Models\Contest;
use App\Models\ContestStanding;
use App\Models\Submission;
use App\Services\StandingCalculator;

class DuelStandingUpdater
{
    public function __construct(private StandingCalculator $calculator) {}

    public function update(Submission $submission, ContestStanding $standing, array $subtasksData): void
    {
        $contest = $submission->contest;
        $userHandle = $submission->user->handle ?? null;

        $standing->refresh();

        $problemIndex = $this->calculator->getProblemIndex($contest, (int) $submission->problem_id);

        if ($problemIndex === false) {
            return;
        }

        $results = $this->calculator->getResults($standing);

        $userMatched = false;

        foreach ($results as &$entry) {
            $entryUser = $entry['handle'] ?? null;
            $entryUser2 = $entry['handle2'] ?? null;

            if ((string) $entryUser === (string) $userHandle) {
                $userMatched = true;

                if ($contest->hasSubtasks()) {
                    if (! isset($entry['problems'][$problemIndex]) || ! is_array($entry['problems'][$problemIndex])) {
                        $entry['problems'][$problemIndex] = [];
                    }

                    foreach ($subtasksData as $sIdx => $subtask) {
                        $earnedPoints = (int) ($subtask['points'] ?? 0);
                        $currentPoints = (int) ($entry['problems'][$problemIndex][$sIdx] ?? 0);
                        $entry['problems'][$problemIndex][$sIdx] = max($currentPoints, $earnedPoints);
                    }
                } else {
                    $score = $this->calculator->calculateScore($submission);
                    $currentScore = (int) ($entry['problems'][$problemIndex]['score'] ?? 0);

                    $entry['problems'][$problemIndex] = [
                        'score' => max($currentScore, $score),
                        'status' => $submission->status,
                    ];
                }

                $entry['total_score'] = $this->calculator->calculateTotalScore($entry['problems']);
                break;
            }

            if ((string) $entryUser2 === (string) $userHandle) {
                $userMatched = true;

                if (! isset($entry['problems2']) || ! is_array($entry['problems2'])) {
                    $countProblems = $contest->problems()->count();
                    $entry['problems2'] = array_fill(0, $countProblems, array_fill(0, 1, 0));
                    $entry['total_score2'] = 0;
                }

                if ($contest->hasSubtasks()) {
                    if (! isset($entry['problems2'][$problemIndex]) || ! is_array($entry['problems2'][$problemIndex])) {
                        $entry['problems2'][$problemIndex] = [];
                    }

                    foreach ($subtasksData as $sIdx => $subtask) {
                        $earnedPoints = (int) ($subtask['points'] ?? 0);
                        $currentPoints = (int) ($entry['problems2'][$problemIndex][$sIdx] ?? 0);
                        $entry['problems2'][$problemIndex][$sIdx] = max($currentPoints, $earnedPoints);
                    }
                } else {
                    $score = $this->calculator->calculateScore($submission);
                    $currentScore = (int) ($entry['problems2'][$problemIndex]['score'] ?? 0);

                    $entry['problems2'][$problemIndex] = [
                        'score' => max($currentScore, $score),
                        'status' => $submission->status,
                    ];
                }

                $entry['total_score2'] = $this->calculator->calculateTotalScore($entry['problems2']);
                break;
            }
        }
        unset($entry);

        if (! $userMatched) {
            return;
        }

        $this->calculator->sortResult($standing, $results);
    }

    public function addUserStanding(ContestStanding $standing, string|array $handle): array
    {
        $contest = $standing->contest;

        if ($contest->status !== 'Active') {
            return $standing->result ?? [];
        }

        $results = collect(
            is_string($standing->result) ? (json_decode($standing->result, true) ?? []) : ($standing->result ?? [])
        );

        $handle1 = is_array($handle) ? ($handle[0] ?? '') : $handle;
        $handle2 = is_array($handle) ? ($handle[1] ?? null) : null;

        $existingIndex = $results->search(function ($item) use ($handle1, $handle2) {
            $itemH1 = $item['handle'] ?? null;
            $itemH2 = $item['handle2'] ?? null;

            if ($itemH1 === $handle1 || $itemH2 === $handle1) {
                return true;
            }

            if ($handle2 && ($itemH1 === $handle2 || $itemH2 === $handle2)) {
                return true;
            }

            return false;
        });

        if ($existingIndex !== false) {
            $existing = $results[$existingIndex];

            if ($handle1 && ! ($existing['handle'] ?? null)) {
                $existing['handle'] = $handle1;
            }
            if ($handle2 && ! ($existing['handle2'] ?? null)) {
                $existing['handle2'] = $handle2;
            }

            if (! isset($existing['problems2']) && ($existing['handle2'] ?? null)) {
                $countProblems = $contest->problems()->count();
                $existing['problems2'] = array_fill(0, $countProblems, array_fill(0, 1, 0));
                $existing['total_score2'] = 0;
            }

            return $this->calculator->sortResult($standing, $results->all());
        }

        $userStanding = $this->createUserStanding($contest, $handle1, $handle2);
        $results->push($userStanding);

        return $this->calculator->sortResult($standing, $results->all());
    }

    private function createUserStanding(Contest $contest, string $handle1, ?string $handle2): array
    {
        $countProblems = $contest->problems()->count();
        $defaultStructure = array_fill(0, 1, 0);
        $hasHandle2 = ! empty($handle2);

        return [
            'handle' => $handle1,
            'handle2' => $handle2,
            'problems' => array_fill(0, $countProblems, $defaultStructure),
            'problems2' => $hasHandle2 ? array_fill(0, $countProblems, $defaultStructure) : null,
            'total_score' => 0,
            'total_score2' => $hasHandle2 ? 0 : null,
        ];
    }
}
