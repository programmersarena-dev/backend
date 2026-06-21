<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contest extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'type_id',
        'name',
        'authorIds',
        'start_date',
        'duration',
        'participantIds',
        'official',
        'active',
    ];

    public function type()
    {
        return $this->belongsTo(ContestType::class, 'type_id', 'id');
    }

    public function hasAttachments()
    {
        return $this->type->name == 'IOI';
    }

    public function hasSubtasks()
    {
        return $this->type->name == 'IOI';
    }

    public function problems()
    {
        return $this->hasMany(Problem::class, 'contest_id');
    }

    public function standings()
    {
        return $this->hasOne(Standing::class);
    }

    public function getStartDateAttribute($value)
    {
        return Carbon::parse($value);
    }

    public function getEndDateAttribute()
    {
        $durationParts = explode(':', $this->duration);
        $hours = (int) $durationParts[0];
        $minutes = (int) $durationParts[1];

        return Carbon::parse($this->start_date)->addHours($hours)->addMinutes($minutes);
    }

    public function getProblemByCharacter($char)
    {
        return $this->problems()->orderBy('id', 'asc')->skip(ord($char) - ord('A'))->first();
    }

    public function getStatus()
    {
        $now = Carbon::now('UTC');
        if ($this->start_date > $now)
            return 'notStarted';
        if ($this->end_date >= $now)
            return 'started';
        return 'ended';
    }

    public function isEnded()
    {
        return $this->end_date > Carbon::now('UTC');
    }

    public function canUserSubmit($userId)
    {
        if ($this->active && $this->getStatus() === 'ended') {
            return true;
        }
        if (
            !$this->active ||
            $this->getStatus() === 'notStarted' ||
            !$this->isUserRegistered($userId) ||
            ($this->type->name === 'Duel' && !$this->isUsersRegisteredInDuel($userId))
        ) {
            return false;
        }
        return true;
    }

    public function isUserRegistered($userId)
    {
        if ($this->isUserOfficial($userId) || $this->isUserUnOfficial($userId))
            return true;
        return false;
    }

    public function isUserOfficial($userId)
    {
        $participants = json_decode($this->participantIds, true);

        if ($this->type->name === 'Classic' || $this->type->name === 'IOI' || $this->type->name === 'ICPC') {
            return in_array($userId, $participants['official'] ?? [], true);
        }

        if ($this->type->name === 'Duel') {
            foreach ($participants['official'] ?? [] as $duo) {
                if (in_array($userId, $duo, true) || in_array($userId . '|X', $duo, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isUserUnOfficial($userId)
    {
        $participants = json_decode($this->participantIds, true);

        if ($this->type->name === 'Classic' || $this->type->name === 'IOI' || $this->type->name === 'ICPC') {
            return in_array($userId, $participants['unofficial'] ?? [], true);
        }

        if ($this->type->name === 'Duel') {
            foreach ($participants['unofficial'] ?? [] as $duo) {
                if (in_array($userId, $duo, true) || in_array($userId . '|X', $duo, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getComponent($username)
    {
        $userId = User::firstWhere('name', $username)->id;

        $participants = json_decode($this->participantIds, true);

        $official = collect($participants['official'] ?? []);
        $unofficial = collect($participants['unofficial'] ?? []);

        $duels = $official->merge($unofficial);

        $opponentIds = $duels->filter(function ($duel) use ($userId) {
            return $duel[0] === $userId || $duel[1] === $userId;
        })->map(function ($duel) use ($userId) {
            return $duel[0] === $userId ? $duel[1] : $duel[0];
        })->unique();

        $firstOpponentId = $opponentIds->first();
        if ($firstOpponentId) {
            return User::find($firstOpponentId)->name;
        }

        return null;
    }

    private function isUsersRegisteredInDuel($userId)
    {
        $participants = json_decode($this->participantIds, true);

        $allDuels = array_merge(
            $participants['official'] ?? [],
            $participants['unofficial'] ?? []
        );

        foreach ($allDuels as $duel) {
            $player1Id = explode('|', $duel[0])[0];
            $player2Id = explode('|', $duel[1])[0];

            if (
                ($player1Id == $userId || $player2Id == $userId) &&
                !str_ends_with($duel[0], '|X') &&
                !str_ends_with($duel[1], '|X')
            ) {
                return true;
            }
        }

        return false;
    }
}
