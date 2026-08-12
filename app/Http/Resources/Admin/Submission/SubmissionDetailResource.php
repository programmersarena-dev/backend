<?php

namespace App\Http\Resources\Admin\Submission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class SubmissionDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = Auth::guard('sanctum')->user();

        $contest = $this->problem?->contest;

        $isContestEnded = $contest?->status === 'Ended';
        $isOwner = $user && $this->user_id === $user->id;
        $canViewDetails = $isContestEnded || $isOwner;

        return [
            'id' => $this->id,
            'handle' => $this->user?->handle,
            'contest' => $contest ? [
                'id' => $contest->id,
                'name' => $contest->name,
            ] : null,
            'problem_char' => $this->problem->char,
            'language' => $this->language,
            'status' => $this->status,
            'time' => ($this->time ?? 0),
            'memory' => ($this->memory ?? 0),
            'sent_time' => $this->created_at?->toIso8601String(),
            'code' => is_string($this->code) ? json_decode($this->code) ?? $this->code : $this->code,
            'outputs' => $canViewDetails ? (is_string($this->outputs) ? json_decode($this->outputs) : $this->outputs) : [],
        ];
    }
}
