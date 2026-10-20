<?php

namespace App\Http\Resources\User\Submission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class SubmissionListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = Auth::guard('sanctum')->user();
        $problem = $this->problem;
        $contest = $problem?->contest;
        $isContestEnded = $contest?->status === 'Ended';
        $isOwner = $user && $this->user_id === $user->id;
        $canViewDetails = $isContestEnded || $isOwner;

        $failedTest = $this->getFirstFailedTest();

        return [
            'id' => $this->id,

            'handle' => $this->user->handle,

            'problem' => [
                'contest_id' => $problem?->contest_id,
                'char' => $problem?->char,
                'name' => $problem?->getTranslation('name'),
            ],

            'language' => $this->language,
            'status' => $this->status,
            'can_poll' => (bool) ($user && $canViewDetails && $contest?->status !== 'Pending'),

            'time' => ($this->time ?? 0),
            'memory' => ($this->memory ?? 0),

            'created_at' => $this->created_at?->toIso8601String(),

            'test' => $canViewDetails ? ($failedTest['test'] ?? null) : null,
            'subtask' => $canViewDetails && ($failedTest['total_subtasks'] ?? 0) > 1 ? ($failedTest['subtask'] ?? null) : null,
        ];
    }
}
