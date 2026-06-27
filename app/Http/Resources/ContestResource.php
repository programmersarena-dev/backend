<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ContestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = Auth::guard('sanctum')->user();

        // 1. Map author names instantly from pre-loaded relationship entries
        $authorNames = $this->authors->pluck('name');

        // 2. Loop through participants natively using our pivot columns (No JSON decoding)
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

        // 3. Mathematical conversion replaces heavy DateTime text object formatting blocks
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;
        $formattedDuration = sprintf('%02d:%02d', $hours, $minutes);

        // 4. Build base response footprint block cleanly
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
            'status' => $this->getStatus(), // Calling optimized model state check helper
            'isRegistered' => $user ? (bool) $this->isUserRegistered($user->id) : false,
        ];

        // 5. Append administration monitoring attributes conditionally
        if ($user && $user->user_type === 'admin') {
            $responsePayload['active'] = (bool) $this->active;
        }

        return $responsePayload;
    }
}
