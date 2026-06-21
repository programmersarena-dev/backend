<?php

namespace App\Http\Resources;

use App\Models\User;
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

        static $users;
        if (!$users) {
            $users = User::all()->keyBy('id');
        }

        $authorNames = collect(json_decode($this->authorIds))->map(function ($userId) use ($users) {
            return $users[$userId]->name ?? 'Undefined';
        });

        $data = json_decode($this->participantIds, true);

        if ($this->type->name === 'Classic' || $this->type->name === 'IOI' || $this->type->name === 'ICPC') {
            $official_participants = collect($data['official'])->map(fn($userId) => $users[$userId]->name ?? 'Undefined');
            $unofficial_participants = collect($data['unofficial'])->map(fn($userId) => $users[$userId]->name ?? 'Undefined');
        } else if ($this->type->name === 'Duel') {
            $official_participants = collect($data['official'])->map(function ($duo) use ($users) {
                if (!is_array($duo) || count($duo) < 2) {
                    return ['Undefined', 'Undefined'];
                }
                return [
                    $users[$duo[0]]->name ?? $users[explode('|', $duo[0])[0]]->name . '|X' ?? 'Undefined',
                    $users[$duo[1]]->name ?? $users[explode('|', $duo[1])[0]]->name . '|X' ?? 'Undefined',
                ];
            });

            $unofficial_participants = collect($data['unofficial'])->map(function ($duo) use ($users) {
                if (!is_array($duo) || count($duo) < 2) {
                    return ['Undefined', 'Undefined'];
                }
                return [
                    $users[$duo[0]]->name ?? $users[explode('|', $duo[0])[0]]->name . '|X' ?? 'Undefined',
                    $users[$duo[1]]->name ?? $users[explode('|', $duo[1])[0]]->name . '|X' ?? 'Undefined',
                ];
            });
        }

        if ($user && $user->user_type === 'admin') {
            return [
                'id' => $this->id,
                'type' => $this->type->name,
                'name' => $this->name,
                'authors' => $authorNames,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'duration' => (new \DateTime($this->duration))->format('H:i'),
                'participants' => [
                    'official' => $official_participants,
                    'unofficial' => $unofficial_participants,
                ],
                'official' => boolval($this->official),
                'attachments' => boolval($this->hasAttachments()),
                'subtasks' => boolval($this->hasSubtasks()),
                'status' => $this->getStatus(),
                'is_registered' => $user ? boolval($this->isUserRegistered($user->id)) : false,
                'active' => boolval($this->active),
            ];
        }
        return [
            'id' => $this->id,
            'type' => $this->type->name,
            'name' => $this->name,
            'authors' => $authorNames,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'duration' => (new \DateTime($this->duration))->format('H:i'),
            'participants' => [
                'official' => $official_participants,
                'unofficial' => $unofficial_participants,
            ],
            'official' => boolval($this->official),
            'attachments' => boolval($this->hasAttachments()),
            'subtasks' => boolval($this->hasSubtasks()),
            'status' => $this->getStatus(),
            'is_registered' => $user ? boolval($this->isUserRegistered($user->id)) : false,
        ];
    }
}
