<?php

namespace App\Services;

use App\Models\Contest;
use App\Models\ContestStanding;
use App\Models\Submission;

class StandingCalculator
{
    public function calculateScore(Submission $submission): int
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

        $minutesPassed = (int) max(0, $contest->start_date->diffInMinutes($submission->created_at));

        $calculatedScore = $problem->score - $minutesPassed;

        return (int) max(0, $calculatedScore);
    }

    public function calculateTotalScore(array $problems): int
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

    public function sortResult(ContestStanding $standing, mixed $result = null): array
    {
        $results = $result ?? $standing->result ?? [];

        $sortedResults = collect($results)
            ->sortByDesc('total_score')
            ->values()
            ->all();

        $standing->update(['result' => $sortedResults]);

        return $sortedResults;
    }

    public function getProblemIndex(Contest $contest, int $submissionProblemId): int|false
    {
        $problemIds = $contest->problems()->pluck('problems.id')->map(fn ($id) => (int) $id)->toArray();

        return array_search($submissionProblemId, $problemIds, true);
    }

    public function getResults(ContestStanding $standing): array
    {
        $result = $standing->result ?? [];

        return is_string($result) ? (json_decode($result, true) ?? []) : $result;
    }
}
