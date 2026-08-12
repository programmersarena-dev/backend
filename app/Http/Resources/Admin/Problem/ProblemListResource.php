<?php

namespace App\Http\Resources\Admin\Problem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProblemListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $contestStatus = $this->contest?->status;

        $tags = ($contestStatus === 'Ended' || $this->contest_id)
            ? $this->tags
            : [];

        $submissions = $this->submissions;

        $submissionsCount = $submissions->count();

        $wrongSubmissionsCount = $submissions
            ->whereIn('status', ['WA'])
            ->count();

        $acceptedProblemsCount = $submissions
            ->whereIn('status', ['AC', '100'])
            ->unique('problem_id')
            ->count();

        return [
            'id' => $this->id,
            'contest_id' => $this->contest_id,
            'code' => $this->code,
            'slug' => $this->slug,
            'char' => $this->char,
            'name' => $this->getTranslation('name'),
            'tags' => $tags,
            'time_limit' => $this->time_limit,
            'memory_limit' => $this->memory_limit,
            'difficulty' => $this->difficulty,
            'score' => $this->score,
            'accepted' => $this->accepted,
            'submissions_count' => $submissionsCount,
            'wrong_submissions_count' => $wrongSubmissionsCount,
            'accepted_submissions_count' => $acceptedProblemsCount,
        ];
    }
}