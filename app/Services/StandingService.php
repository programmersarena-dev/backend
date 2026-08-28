<?php

namespace App\Services;

use App\Models\ContestStanding;
use App\Models\Submission;
use App\Services\Updaters\ClassicStandingUpdater;
use App\Services\Updaters\DuelStandingUpdater;
use App\Services\Updaters\ICPCStandingUpdater;
use App\Services\Updaters\IOIStandingUpdater;
use Illuminate\Support\Facades\Log;

class StandingService
{
    public function __construct(
        private ClassicStandingUpdater $classicUpdater,
        private IOIStandingUpdater $ioiUpdater,
        private DuelStandingUpdater $duelUpdater,
        private ICPCStandingUpdater $icpcUpdater,
        private StandingCalculator $calculator,
    ) {}

    public function updateContestStandings(Submission $submission, mixed $subtasksData): void
    {
        try {
            $contest = $submission->contest;
            if (! $contest) {
                return;
            }

            if (is_string($subtasksData)) {
                $subtasksData = json_decode($subtasksData, true) ?? [];
            } elseif (! is_array($subtasksData)) {
                $subtasksData = [];
            }

            $userHandle = $submission->user->handle ?? null;
            if (! $userHandle) {
                return;
            }

            $standing = ContestStanding::firstOrCreate(
                ['contest_id' => $contest->id],
                ['result' => []]
            );

            if (! $contest->isICPC() && ! $contest->isDuel()) {
                $this->addUserStanding($standing, $userHandle);
            }

            if ($contest->isICPC()) {
                $this->icpcUpdater->update($submission, $standing, $subtasksData);

                return;
            }

            if ($contest->isDuel()) {
                $this->duelUpdater->update($submission, $standing, $subtasksData);

                return;
            }

            if ($contest->hasSubtasks()) {
                $this->ioiUpdater->update($submission, $standing, $subtasksData);

                return;
            }

            $this->classicUpdater->update($submission, $standing, $subtasksData);

        } catch (\Throwable $e) {
            Log::error("Failed to update standings for submission #{$submission->id}: ".$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function addUserStanding(ContestStanding $standing, string|array $handle): array
    {
        $contest = $standing->contest;

        if ($contest->status !== 'Active') {
            return $standing->result ?? [];
        }

        $contestType = $contest->type->name ?? 'Classic';

        if ($contestType === 'ICPC') {
            return $standing->result ?? [];
        }

        if ($contestType === 'Duel') {
            return $this->duelUpdater->addUserStanding($standing, $handle);
        }

        $results = collect(
            is_string($standing->result) ? (json_decode($standing->result, true) ?? []) : ($standing->result ?? [])
        );

        $searchHandle = is_array($handle) ? ($handle[0] ?? '') : $handle;

        if (! $this->isUserExists($searchHandle, $results)) {
            $userStanding = match ($contestType) {
                'Classic' => $this->classicUpdater->createUserStanding($contest, $searchHandle),
                'IOI' => $this->ioiUpdater->createUserStanding($contest, $searchHandle),
                default => throw new \InvalidArgumentException("Unsupported contest type: {$contestType}"),
            };

            $results->push($userStanding);
        }

        return $this->calculator->sortResult($standing, $results->all());
    }

    public function removeUserStanding(ContestStanding $standing, string $handle, string $contestType): array
    {
        $result = $standing->result ?? [];
        $results = collect(is_string($result) ? (json_decode($result, true) ?? []) : $result);

        if ($contestType === 'ICPC') {
            $filtered = $results->reject(function ($item) use ($handle) {
                return in_array($handle, $item['members'] ?? []);
            });
        } else {
            $filtered = $results->reject(function ($item) use ($handle, $contestType) {
                if ($contestType === 'Classic' || $contestType === 'IOI') {
                    return ($item['handle'] ?? null) === $handle;
                }
                if ($contestType === 'Duel') {
                    return ($item['handle'] ?? null) === $handle || ($item['handle2'] ?? null) === $handle;
                }

                return false;
            });
        }

        return $this->calculator->sortResult($standing, $filtered->all());
    }

    public function userContestResult(ContestStanding $standing, string $username): ?array
    {
        $results = $standing->result ?? [];

        if ($standing->contest->isICPC()) {
            foreach ($results as $entry) {
                if (in_array($username, $entry['members'] ?? [])) {
                    $entry['place'] = array_search($entry, $results, true) + 1;

                    return $entry;
                }
            }

            return null;
        }

        foreach ($results as $index => $user) {
            if (($user['handle'] ?? null) === $username || ($user['handle2'] ?? null) === $username) {
                $user['place'] = $index + 1;

                return $user;
            }
        }

        return null;
    }

    public function sortResult(ContestStanding $standing, mixed $result = null): array
    {
        return $this->calculator->sortResult($standing, $result);
    }

    private function isUserExists(string $handle, $results)
    {
        return (bool) $results->first(function ($item) use ($handle) {
            return ($item['handle'] ?? null) === $handle;
        });
    }
}
