<?php

namespace App\Models;

use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contest extends Model
{
    use HasFactory;

    protected $casts = [
        'start_date' => 'datetime',
        'official' => 'boolean',
        'active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships (Optimized for Pivot Tables)
    |--------------------------------------------------------------------------
    */

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contest_author');
    }

    /**
     * Get all registered participants via the optimized pivot table.
     * We load pivot fields 'is_official' and 'opponent_id' to support Duel and standard modes natively.
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contest_user')
            ->withPivot('is_official', 'old_rating', 'rating_change', 'is_official', 'opponent_id')
            ->withTimestamps();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ContestType::class, 'type_id');
    }

    public function problems(): HasMany
    {
        return $this->hasMany(Problem::class, 'contest_id');
    }

    public function standings(): HasOne
    {
        return $this->hasOne(Standing::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Computed Attributes
    |--------------------------------------------------------------------------
    */

    /**
     * Computes the exact end date using the optimized duration_minutes field.
     * Uses Laravel's modern memoized attribute caching mechanism.
     */
    protected function endDate(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->start_date?->copy()->addMinutes((int) $this->duration_minutes)
        )->shouldCache();
    }

    public function hasAttachments(): bool
    {
        return $this->type?->name === 'IOI';
    }

    public function hasSubtasks(): bool
    {
        return $this->type?->name === 'IOI';
    }

    /**
     * Efficiently grabs a problem by alphabetical offset via SQL directly (no application-level loops).
     */
    public function getProblemByCharacter(string $char): ?Problem
    {
        $offset = ord(strtoupper($char)) - ord('A');

        return $this->problems()
            ->orderBy('id', 'asc')
            ->offset($offset)
            ->first();
    }

    public function getStatus(): string
    {
        $now = Carbon::now('UTC');

        if ($this->start_date > $now) {
            return 'notStarted';
        }
        if ($this->end_date >= $now) {
            return 'started';
        }
        return 'ended';
    }

    public function isEnded(): bool
    {
        return Carbon::now('UTC') > $this->end_date;
    }

    /*
    |--------------------------------------------------------------------------
    | Core Business Logic & Registration Rules (Sub-millisecond Performance)
    |--------------------------------------------------------------------------
    */

    public function canUserSubmit(int|string $userId): bool
    {
        $status = $this->getStatus();

        if ($this->active && $status === 'ended') {
            return true;
        }

        if (!$this->active || $status === 'notStarted') {
            return false;
        }

        return $this->isUserRegistered($userId);
    }

    /**
     * Checks registration instantly via an indexed database lookup instead of parsing giant JSON blobs.
     */
    public function isUserRegistered(int|string $userId): bool
    {
        return DB::table('contest_user')
            ->where('contest_id', $this->id)
            ->where('user_id', $userId)
            ->exists();
    }

    public function isUserOfficial(int|string $userId): bool
    {
        return $this->participants()
            ->wherePivot('user_id', $userId)
            ->exists();
    }

    public function isUserUnOfficial(int|string $userId): bool
    {
        return $this->participants()
            ->where('user_id', $userId)
            ->where('pivot_is_official', false)
            ->exists();
    }

    /**
     * Instantly grabs the active opponent's name in Duel formats.
     * Replaces multi-loop array filtering and N+1 user model lookups with a fast relationship pluck.
     */
    public function getComponent(string $username): ?string
    {
        $userId = User::where('name', $username)->value('id');
        if (!$userId)
            return null;

        $pivot = $this->participants()->where('user_id', $userId)->first()?->pivot;

        if ($pivot && $pivot->opponent_id) {
            return User::where('id', $pivot->opponent_id)->value('name');
        }

        return null;
    }
}
