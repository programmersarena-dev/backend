<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'country' => $this->profile->country ? $this->profile->country->code : '',
            'participationCount' => $this->rating ? sizeof(json_decode($this->rating->contest_ratings)) : 0,
            'currentRating' => $this->rating ? $this->rating->current_rating : 0,
        ];
    }
}
