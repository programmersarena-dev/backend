<?php

namespace App\Http\Resources\User\Problem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ProblemListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $contestStatus = $this->contest?->status;

        $tags = ($contestStatus === 'Ended' || $this->contest_id)
            ? is_array($this->tags) ? $this->tags : json_decode($this->tags, true)
            : [];

        $acceptedProblemsCount = $this->submissions
            ->whereIn('status', ['AC', '100'])
            ->unique('user_id')
            ->count();

        $userId = Auth::guard('sanctum')->user()?->id;

        $tried = $userId ? $this->submissions()->where('user_id', $userId)->exists() : false;

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
            'tried' => $tried,
            'accepted_submissions_count' => $acceptedProblemsCount,
        ];
    }
}