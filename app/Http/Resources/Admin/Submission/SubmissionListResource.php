<?php

namespace App\Http\Resources\Admin\Submission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $problem = $this->problem;
        $failedTest = $this->getFirstFailedTest();

        return [
            'id' => $this->id,

            'handle' => $this->user->handle,

            'problem' => [
                'contest_id' => $problem?->contest_id,
                'char' => $problem?->char,
                'name' => $problem?->getTranslation("name"),
            ],

            'language' => $this->language,
            'status' => $this->status,

            'time' => ($this->time ?? 0),
            'memory' => ($this->memory ?? 0),

            'created_at' => $this->created_at?->toIso8601String(),

            'test' => $failedTest['test'] ?? null,
            'subtask' => ($failedTest['total_subtasks'] ?? 0) > 1 ? ($failedTest['subtask'] ?? null) : null,
        ];
    }
}
