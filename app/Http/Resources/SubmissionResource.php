<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
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
            'language' => $this->language,
            'status' => $this->status,

            'time' => ($this->time ?? 0),
            'memory' => ($this->memory ?? 0),

            'created_at' => $this->created_at?->toIso8601String(),

            'username' => $this->whenLoaded(
                'user',
                fn() => $this->user->handle,
                'Unknown User'
            ),

            'problem' => $this->whenLoaded('problem', fn() => [
                'contest_id' => $this->problem->contest_id,
                'char' => $this->problem->char() ?? 'A',
                'name' => $this->problem->getTranslation("name") ?? 'Unknown Problem',
            ]),
        ];
    }
}
