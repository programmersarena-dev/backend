<?php

namespace App\Services\Updaters;

use App\Models\Contest;
use App\Models\ContestStanding;
use App\Models\Group;
use App\Models\Problem;
use App\Models\Submission;
use App\Services\StandingCalculator;

class ICPCStandingUpdater
{
    public function __construct(private StandingCalculator $calculator) {}

    public function update(Submission $submission, ContestStanding $standing, array $subtasksData): void
    {
        $contest = $submission->contest;
        $user = $submission->user;

        $group = Group::where('contest_id', $contest->id)
            ->whereHas('members', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->with('members')
            ->first();

        if (! $group) {
            return;
        }

        $groupName = $group->name;
        $memberHandles = $group->members->pluck('handle')->sort()->values()->all();

        $this->addGroupStanding($standing, $groupName, $memberHandles);

        $standing->refresh();

        $problemIndex = $this->calculator->getProblemIndex($contest, (int) $submission->problem_id);

        if ($problemIndex === false) {
            return;
        }

        $results = $this->calculator->getResults($standing);

        $groupMatched = false;

        foreach ($results as &$entry) {
            if (($entry['group_name'] ?? null) === $groupName) {
                $groupMatched = true;

                $score = $this->calculateGroupScoreForProblem($submission, $group);
                $currentScore = (int) ($entry['problems'][$problemIndex]['score'] ?? 0);

                if ($score > $currentScore || in_array($submission->status, Submission::ACCEPTED_STATUSES, true)) {
                    $entry['problems'][$problemIndex] = [
                        'score' => max($currentScore, $score),
                        'status' => $submission->status,
                    ];
                }

                $entry['total_score'] = $this->calculator->calculateTotalScore($entry['problems']);
                break;
            }
        }
        unset($entry);

        if (! $groupMatched) {
            return;
        }

        $this->calculator->sortResult($standing, $results);
    }

    public function addGroupStanding(ContestStanding $standing, string $groupName, array $memberHandles): array
    {
        $results = collect(
            is_string($standing->result) ? (json_decode($standing->result, true) ?? []) : ($standing->result ?? [])
        );

        $existingIndex = $results->search(fn ($item) => ($item['group_name'] ?? null) === $groupName);

        if ($existingIndex !== false) {
            $existing = $results[$existingIndex];
            $existing['members'] = $memberHandles;
            $results[$existingIndex] = $existing;

            $standing->result = $results->all();
            $standing->save();

            return $this->calculator->sortResult($standing, $results->all());
        }

        $contest = $standing->contest;
        $countProblems = $contest->problems()->count();
        $defaultStructure = array_fill(0, 1, 0);

        $groupStanding = [
            'group_name' => $groupName,
            'members' => $memberHandles,
            'problems' => array_fill(0, $countProblems, $defaultStructure),
            'total_score' => 0,
        ];

        $results->push($groupStanding);

        return $this->calculator->sortResult($standing, $results->all());
    }

    private function calculateGroupScoreForProblem(Submission $submission, Group $group): int
    {
        $contest = $submission->contest;
        $problem = $submission->problem;

        $bestScore = 0;

        foreach ($group->members as $member) {
            $memberBest = $this->calculateScoreForUserProblem($member->id, $problem->id, $contest);
            $bestScore = max($bestScore, $memberBest);
        }

        return $bestScore;
    }

    private function calculateScoreForUserProblem(int $userId, int $problemId, Contest $contest): int
    {
        $bestSubmission = Submission::where('user_id', $userId)
            ->where('problem_id', $problemId)
            ->whereIn('status', (array) Submission::ACCEPTED_STATUSES)
            ->where('created_at', '>=', $contest->start_date)
            ->orderBy('created_at', 'asc')
            ->first();

        if (! $bestSubmission) {
            return 0;
        }

        $problem = Problem::find($problemId);
        if (! $problem || ! $contest->start_date) {
            return $problem?->score ?? 0;
        }

        $minutesPassed = (int) max(0, $contest->start_date->diffInMinutes($bestSubmission->created_at));
        $calculatedScore = ($problem->score ?? 0) - $minutesPassed;

        return (int) max(0, $calculatedScore);
    }
}
