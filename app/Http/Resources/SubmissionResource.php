<?php

namespace App\Http\Resources;

use App\Models\Problem;
use App\Models\User;
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
            'created_at' => $this->created_at,
            'username' => $this->user->name,
            'problem' => [
                'contest_id' => $this->problem->contest_id,
                'char' => $this->problem->char(),
                'name' => $this->problem->getTranslation("name"),
            ],
            'language' => $this->language,
            'verdict' => $this->verdict,
            'time' => $this->time().' ms',
            'memory' => $this->memory().' KB',
        ];
    }
}
