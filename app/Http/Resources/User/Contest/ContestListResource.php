<?php

namespace App\Http\Resources\User\Contest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ContestListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = Auth::guard('sanctum')->user();

        $authorNames = $this->authors->pluck('name');

        $officialParticipants = [];
        $unofficialParticipants = [];

        if ($this->type?->name === 'Duel') {
            $participantMap = $this->participants->keyBy('id');

            foreach ($this->participants as $participant) {
                $opponentId = $participant->pivot->opponent_id;
                $opponentName = $participantMap->get($opponentId)?->name ?? 'Undefined';

                $pairing = [$participant->name, $opponentName];

                if ($participant->pivot->is_official) {
                    $officialParticipants[] = $pairing;
                } else {
                    $unofficialParticipants[] = $pairing;
                }
            }
        } else {
            foreach ($this->participants as $participant) {
                if ($participant->pivot->is_official) {
                    $officialParticipants[] = $participant->name;
                } else {
                    $unofficialParticipants[] = $participant->name;
                }
            }
        }

        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;
        $formattedDuration = sprintf('%02d:%02d', $hours, $minutes);

        $responsePayload = [
            'id' => $this->id,
            'type' => $this->type?->name,
            'name' => $this->name,
            'authors' => $authorNames,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'duration' => $formattedDuration,
            'participants' => [
                'official' => $officialParticipants,
                'unofficial' => $unofficialParticipants,
            ],
            'official' => (bool) $this->official,
            'attachments' => (bool) $this->hasAttachments(),
            'subtasks' => (bool) $this->hasSubtasks(),
            'status' => $this->getStatus(),
            'is_registered' => $user ? (bool) $this->isUserRegistered($user->id) : false,
        ];

        if ($user && $user->user_type === 'admin') {
            $responsePayload['active'] = (bool) $this->active;
        }

        return $responsePayload;
    }
}
