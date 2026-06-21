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
        return [
            'contest_id' => $this->contest->id,
            'char' => $this->char(),
            'name' => $this->getTranslation("name"),
            'tags' => $this->contest->getStatus() == 'ended' ? json_decode($this->tags) : [],
            'difficulty' => $this->score,
            'solved' => $this->solved(),
            'accepted_submissions' => $this->accepted_submissions_count,
        ];
    }
}
