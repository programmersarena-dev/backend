<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'handle'                 => $this->handle,
            'name'                   => $this->name,
            'image'                  => $this->profile?->image ? asset('storage/' . $this->profile->image) : '',
            'firstName'              => $this->profile?->first_name ?? '',
            'lastName'               => $this->profile?->last_name ?? '',
            'country'                => $this->profile?->country?->name ?? '',
            'is_online'              => $this->last_activity && $this->last_activity->gte(now()->subMinutes(5)) ? 1 : 0,
            'acceptedProblemsCount'  => $this->accepted_problems_count,
            'current_rating'         => $this->rating?->current_rating ?? 0,
        ];
    }
}