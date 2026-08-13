<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\ContestStanding;
use App\Models\Contest;
use App\Models\Problem;

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
                // addUserStanding lives on this service now, not on the
                // model — $standing->addUserStanding(...) was calling a
                // method that doesn't exist on ContestStanding at all.
                $this->addUserStanding($standing, $userHandle);
            } catch (\Throwable $e) {
                // Was silently swallowed with no logging — meaning the
                // class-name mismatch bug above (and any future bug in
                // addUserStanding) would fail completely invisibly, with
                // no trace to explain why a user never appears in
                // standings. Logging it doesn't change the soft-fail
                // behavior (still returns rather than crashing the whole
                // update), just makes failures diagnosable.
                Log::warning("addUserStanding failed for submission #{$submission->id}: " . $e->getMessage());
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

                $entryUser2 = $entry['handle2'] ?? null;

                if (
                    (string) $entryUser === (string) $userHandle
                    || (string) $entryUser2 === (string) $userHandle
                ) {
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
                        $score = $this->calculateScore($submission);
                        $currentScore = (int) ($entry['problems'][$problemIndex]['score'] ?? 0);

                        $entry['problems'][$problemIndex] = [
                            'score' => $currentScore + $score,
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

            $this->sortResult($standing, $results);

        } catch (\Throwable $e) {
            Log::error("Failed to update standings for submission #{$submission->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function calculateScore(Submission $submission): int
    {
        $submission->loadMissing(['problem', 'contest', 'user']);
        $problem = $submission->problem;
        $userId = $submission->user->id;

        $hasAcceptedSubmission = Submission::where('user_id', $userId)
            ->where('problem_id', $problem->id)
            ->whereIn('status', (array) Submission::ACCEPTED_STATUSES)
            ->where('id', '!=', $submission->id)
            ->exists();

        if ($hasAcceptedSubmission) {
            return 0;
        }

        $isAccepted = in_array($submission->status, Submission::ACCEPTED_STATUSES, true);
        if (! $isAccepted) {
            return -1;
        }

        $contest = $submission->contest;
        if (! $contest || ! $contest->start_date) {
            return $problem->score;
        }

        $minutesPassed = (int) max(0, $contest->start_date->diffInMinutes($submission->created_at, false));

        $calculatedScore = $problem->score - $minutesPassed;

        return (int) max(0, $calculatedScore);
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

    /**
     * Add a user or team standing row if not already present.
     */
    public function addUserStanding(ContestStanding $standing, string|array $handle): array
    {
        $contest = $standing->contest;

        if ($contest->status !== 'Active') {
            throw new \Exception('Contest must be started before adding users.');
        }

        $contestType = $contest->type->name ?? 'Classic';

        $results = collect(
            is_string($standing->result) ? (json_decode($standing->result, true) ?? []) : ($standing->result ?? [])
        );

        $searchHandle = is_array($handle) ? ($handle[0] ?? '') : $handle;

        if (!$this->isUserExists($searchHandle, $results)) {
            $userStanding = match ($contestType) {
                'Classic' => $this->addUserStandingToClassicContest($contest, $searchHandle),
                'IOI' => $this->addUserStandingToIOIContest($contest, $searchHandle),
                'Duel' => $this->addUserStandingToDuelContest($contest, ...(array) $handle),
                default => throw new \InvalidArgumentException("Unsupported contest type: {$contestType}"),
            };

            $results->push($userStanding);
        }

        return $this->sortResult($standing, $results->all());
    }

    private function isUserExists(string $handle, $results)
    {
        return (bool) $results->first(function ($item) use ($handle) {
            return ($item['handle'] ?? null) === $handle;
        });
    }

    private function addUserStandingToClassicContest(Contest $contest, string $handle): array
    {
        $countProblems = $contest->problems()->count();
        $defaultStructure = array_fill(0, 1, 0);

        return [
            'handle' => $handle,
            'problems' => array_fill(0, $countProblems, $defaultStructure),
            'total_score' => 0,
        ];
    }

    private function addUserStandingToIOIContest(Contest $contest, string $handle): array
    {
        $countProblems = $contest->problems()->count();
        $defaultStructure = array_fill(0, 1, 0);

        return [
            'handle' => $handle,
            'problems' => array_fill(0, $countProblems, $defaultStructure),
            'total_score' => 0,
        ];
    }

    private function addUserStandingToDuelContest(Contest $contest, string $handle1, string $handle2): array
    {
        $countProblems = $contest->problems()->count();
        $defaultStructure = array_fill(0, 1, 0);

        return [
            'handle' => $handle1,
            'handle2' => $handle2,
            'problems' => array_fill(0, $countProblems, $defaultStructure),
            'total_score' => 0,
        ];
    }

    /**
     * Remove a user from the standings table and persist changes.
     */
    public function removeUserStanding(ContestStanding $standing, string $handle, string $contestType): array
    {
        $result = $standing->result ?? [];
        $results = collect(is_string($result) ? (json_decode($result, true) ?? []) : $result);

        $filtered = $results->reject(function ($item) use ($handle, $contestType) {
            if ($contestType === 'Classic' || $contestType === 'IOI') {
                return ($item['handle'] ?? null) === $handle;
            }
            if ($contestType === 'Duel') {
                return ($item['handle'] ?? null) === $handle || ($item['handle2'] ?? null) === $handle;
            }
            return false;
        });

        return $this->sortResult($standing, $filtered->all());
    }

    /**
     * Get standing details and rank for a specific user.
     */
    public function userContestResult(ContestStanding $standing, string $username): ?array
    {
        $results = $standing->result ?? [];

        foreach ($results as $index => $user) {
            if (($user['handle'] ?? null) === $username) {
                $user['place'] = $index + 1;
                return $user;
            }
        }

        return null;
    }

    public function sortResult(ContestStanding $standing, mixed $result = null): array
    {
        $data = $result ?? $standing->result ?? [];

        $resultsCollection = collect(
            is_string($data) ? (json_decode($data, true) ?? []) : $data
        );

        $sortedResults = $resultsCollection
            ->sortByDesc('total_score')
            ->values()
            ->all();

        $standing->update(['result' => $sortedResults]);

        return $sortedResults;
    }
}