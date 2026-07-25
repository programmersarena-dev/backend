<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ContestDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = Auth::guard('sanctum')->user();
        $isAdmin = $user && $user->user_type === 'admin';

        // 1. Fetch author names cleanly via the loaded relation (or fallback)
        $authorNames = $this->relationLoaded('authors')
            ? $this->authors->pluck('name')
            : $this->authors()->pluck('name');

        // 2. Resolve participants via Eloquent relation instead of parsing JSON strings
        $participants = $this->resolveParticipants();

        // 3. Format duration cleanly
        $formattedDuration = is_numeric($this->duration_minutes)
            ? sprintf('%02d:%02d', floor($this->duration_minutes / 60), $this->duration_minutes % 60)
            : $this->duration;

        return [
            'id' => $this->id,
            'type' => $this->type?->name,
            'name' => $this->name,
            'authors' => $authorNames,
            'start_date' => $this->start_date?->toISOString(),
            'end_date' => $this->end_date?->toISOString(),
            'duration' => $formattedDuration,
            'participants' => $participants,
            'official' => (bool) $this->official,
            'attachments' => $this->hasAttachments(),
            'subtasks' => $this->hasSubtasks(),
            'status' => $this->getStatus(),

            // Conditional field using Laravel API Resource helpers
            $this->mergeWhen($isAdmin, [
                'active' => (bool) $this->active,
            ]),
        ];
    }

    /**
     * Group participants cleanly using loaded relations.
     */
    protected function resolveParticipants(): array
    {
        // If relation isn't loaded, load participants with pivot fields
        $participants = $this->relationLoaded('participants')
            ? $this->participants
            : $this->participants()->get();

        $typeName = $this->type?->name;

        // Standard formats (Classic, IOI, ICPC)
        if (in_array($typeName, ['Classic', 'IOI', 'ICPC'])) {
            return [
                'official' => $participants->where('pivot.is_official', true)->pluck('name')->values(),
                'unofficial' => $participants->where('pivot.is_official', false)->pluck('name')->values(),
            ];
        }

        // Duel format (Pairings)
        if ($typeName === 'Duel') {
            $grouped = $participants->groupBy(fn($p) => $p->pivot->is_official ? 'official' : 'unofficial');

            return [
                'official' => $this->formatDuelPairs($grouped->get('official', collect())),
                'unofficial' => $this->formatDuelPairs($grouped->get('unofficial', collect())),
            ];
        }

        return [
            'official' => [],
            'unofficial' => [],
        ];
    }

    /**
     * Format duel participants into pairing tuples [Player 1, Player 2].
     */
    protected function formatDuelPairs($participantCollection)
    {
        return $participantCollection->map(function ($player) use ($participantCollection) {
            $opponentId = $player->pivot->opponent_id;
            $opponent = $participantCollection->firstWhere('id', $opponentId);

            return [
                $player->name ?? 'Undefined',
                $opponent?->name ?? 'Undefined',
            ];
        })->values();
    }
}
