<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProblemsetStandingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username ?? $this->name,
            'accepted_problems_count' => (int) ($this->accepted_problems_count ?? 0),
            'avatar' => $this->avatar ?? null,
            'country_code' => $this->country_code ?? null,
        ];
    }
}
