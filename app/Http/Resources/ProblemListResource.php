<?php

namespace App\Http\Resources;

use App\Models\Problem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ProblemListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isContestEnded = $this->isContestEnded();

        return [
            'contest_id' => $this->contest_id ?? $this->contest?->id,
            'char' => $this->char(),
            'name' => $this->getTranslation('name'),

            'tags' => $isContestEnded ? (is_string($this->tags) ? json_decode($this->tags, true) : ($this->tags ?? [])) : [],
            'difficulty' => $this->score,

            'solved' => $this->is_solved ?? $this->isSolvedBy(),

            'accepted_submissions' => $this->accepted_submissions_count,
        ];
    }
}
