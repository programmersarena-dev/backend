<?php

namespace App\Http\Resources\User\Contest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContestRatingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'handle' => $this->handle,
            'country' => $this->profile?->country ? $this->profile->country->code : '',
            'participationCount' => $this->contest_ratings_count ?? 0,
            'currentRating' => $this->current_rating ?? 0,
        ];
    }
}
