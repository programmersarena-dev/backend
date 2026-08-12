<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContestStanding extends Model
{
    use HasFactory;

    /**
     * Disable default timestamps if using custom or single updated_at column.
     */
    public $timestamps = false;

    protected $fillable = [
        'contest_id',
        'result',
    ];

    /**
     * Automatic JSON casting for Eloquent.
     */
    protected $casts = [
        'result' => 'array',
    ];

    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';


    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    /**
     * Add a user or team standing row if not already present.
     */
    public function addUserStanding(string|array $handle): array
    {
        if($this->contest->status != "started") {
            throw new \Exception("Contest must be started before adding users.");
        }

        $contestType = $contest->type->name ?? 'Classic';

        $results = collect(
            is_string($this->result) ? (json_decode($this->result, true) ?? []) : ($this->result ?? [])
        );

        $searchHandle = is_array($handle) ? ($handle[0] ?? '') : $handle;

        if (!isUserExists($searchHandle, $results)) {
            $userStanding = match ($contestType) {
                'Classic' => $this->addUserStandingToClassicContest($searchHandle),
                'IOI' => $this->addUserStandingToIOIContest($searchHandle),
                'Duel' => $this->addUserStandingToDuelContest(...(array)$searchHandle),
                default => throw new \InvalidArgumentException("Unsupported contest type: {$contestType}"),
            };

            $results->push($userStanding);
        }

        return $this->sortResult($results->all());
    }

    private function isUserExists(string $handle, $results)
    {
        return $results->first(function ($item) use ($handle) {
            return $item['handle'] === $handle;
        });
    }

    private function addUserStandingToClassicContest($handle)
    {
        $contest = $this->contest;
        $countProblems = $contest->problems()->count();
        $defaultStructure = array_fill(0, 1, 0);

        return [
            'handle' => $handle,
            'problems' => array_fill(0, $countProblems, $defaultStructure),
            'total_score' => 0,
        ];
    }

    private function addUserStandingToIOIContest($handle)
    {
        $contest = $this->contest;
        $problems = $contest->problems();
        $countProblems = $problems->count();
        $defaultStructure = array_fill(0, 1, 0);

        return [
            'handle' => $handle,
            'problems' => array_fill(0, $countProblems, $defaultStructure),
            'total_score' => 0,
        ];
    }

    private function addUserStandingToDuelContest($handle1, $handle2)
    {
        $contest = $this->contest;
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
    public function removeUserStanding(string $handle, string $contestType): array
    {
        $results = collect($this->result ?? []);

        $filtered = $results->reject(function ($item) use ($handle, $contestType) {
            if ($contestType === 'Classic') {
                return ($item['handle'] ?? null) === $handle;
            }
            if ($contestType === 'Duel') {
                return ($item['handle'] ?? null) === $handle || ($item['handle2'] ?? null) === $handle;
            }
            return false;
        });

        return $this->sortResult($filtered);
    }

    /**
     * Get standing details and rank for a specific user.
     */
    public function userContestResult(string $username): ?array
    {
        $results = $this->result ?? [];

        foreach ($results as $index => $user) {
            if (($user['handle'] ?? null) === $username) {
                $user['place'] = $index + 1;
                return $user;
            }
        }

        return null;
    }

    public function sortResult(mixed $result = null): array
    {
        $data = $result ?? $this->result ?? [];

        $resultsCollection = collect(
            is_string($data) ? (json_decode($data, true) ?? []) : $data
        );

        $sortedResults = $resultsCollection
            ->sortByDesc('total_score')
            ->values()
            ->all();

        $this->update(['result' => $sortedResults]);

        return $sortedResults;
    }
}
