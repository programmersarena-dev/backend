<?php

namespace App\Services\Updaters;

use App\Models\Contest;
use App\Models\ContestStanding;
use App\Models\Submission;
use App\Services\StandingCalculator;

class ClassicStandingUpdater
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

        foreach ($results as &$entry) {
            $entryUser = $entry['handle'] ?? null;

            if (is_array($entryUser)) {
                $entryUser = $entryUser[0] ?? null;
            }

            if ((string) $entryUser === (string) $userHandle) {
                $score = $this->calculator->calculateScore($submission);
                $currentScore = (int) ($entry['problems'][$problemIndex]['score'] ?? 0);

                $entry['problems'][$problemIndex] = [
                    'score' => max($currentScore, $score),
                    'status' => $submission->status,
                ];

                $entry['total_score'] = $this->calculator->calculateTotalScore($entry['problems']);
                break;
            }
        }
        unset($entry);

        $this->calculator->sortResult($standing, $results);
    }

    public function createUserStanding(Contest $contest, string $handle): array
    {
        $countProblems = $contest->problems()->count();
        $defaultStructure = array_fill(0, 1, 0);

        return [
            'handle' => $handle,
            'problems' => array_fill(0, $countProblems, $defaultStructure),
            'total_score' => 0,
        ];
    }
}
