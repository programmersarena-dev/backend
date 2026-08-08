<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Contest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'type_id',
        'name',
        'start_date',
        'duration_minutes',
        'official',
        'active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'start_date' => 'datetime',
        'official'   => 'boolean',
        'active'     => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contest_author');
    }

    /**
     * Participants (users registered for the contest).
     * The pivot table is 'contest_user' with extra fields.
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contest_user')
            ->withPivot('is_official', 'old_rating', 'rating_change', 'opponent_id')
            ->withTimestamps();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ContestType::class, 'type_id');
    }

    public function problems(): HasMany
    {
        return $this->hasMany(Problem::class);
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
     * Computed end date from start_date + duration_minutes.
     */
    protected function endDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->start_date?->copy()->addMinutes((int) $this->duration_minutes)
        )->shouldCache();
    }

    /**
     * Check if the contest has attachments (e.g., for IOI style).
     */
    public function hasAttachments(): bool
    {
        return $this->type?->name === 'IOI';
    }

    public function hasSubtasks(): bool
    {
        return $this->type?->name === 'IOI';
    }

    /**
     * Get a problem by alphabetical index (A, B, C, ...).
     */
    public function getProblemByCharacter(string $char): ?Problem
    {
        $offset = ord(strtoupper($char)) - ord('A');
        return $this->problems()
            ->orderBy('id')
            ->offset($offset)
            ->first();
    }

    /**
     * Get the contest status based on current time.
     */
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
    | Registration & Participation Checks (Performance Optimized)
    |--------------------------------------------------------------------------
    */

    /**
     * Check if a user can submit (active, running, and registered).
     */
    public function canUserSubmit(int|string $userId): bool
    {
        $status = $this->getStatus();

        if ($this->active && $status === 'ended') {
            return true; // allow viewing submissions after end
        }

        if (!$this->active || $status === 'notStarted') {
            return false;
        }

        return $this->isUserRegistered($userId);
    }

    /**
     * Check if a user is registered (fast direct DB query).
     */
    public function isUserRegistered(int|string $userId): bool
    {
        return DB::table('contest_user')
            ->where('contest_id', $this->id)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Check if a user is registered as official.
     */
    public function isUserOfficial(int|string $userId): bool
    {
        return DB::table('contest_user')
            ->where('contest_id', $this->id)
            ->where('user_id', $userId)
            ->where('is_official', true)
            ->exists();
    }

    /**
     * Check if a user is registered as unofficial.
     */
    public function isUserUnOfficial(int|string $userId): bool
    {
        return DB::table('contest_user')
            ->where('contest_id', $this->id)
            ->where('user_id', $userId)
            ->where('is_official', false)
            ->exists();
    }

    /**
     * Get the opponent name for a given username (duel mode).
     */
    public function getComponent(string $username): ?string
    {
        $userId = User::where('name', $username)->value('id');
        if (!$userId) {
            return null;
        }

        $opponentId = DB::table('contest_user')
            ->where('contest_id', $this->id)
            ->where('user_id', $userId)
            ->value('opponent_id');

        if ($opponentId) {
            return User::where('id', $opponentId)->value('name');
        }

        return null;
    }

    /**
     * Helper to register a participant (used in controller).
     * This centralizes pivot insertion logic.
     */
    public function registerParticipant(int|string $userId, bool $isOfficial, ?int $opponentId = null): void
    {
        $this->participants()->attach($userId, [
            'is_official' => $isOfficial,
            'opponent_id' => $opponentId,
        ]);
    }

    /**
     * Helper to unregister a participant.
     */
    public function unregisterParticipant(int|string $userId): void
    {
        $this->participants()->detach($userId);
    }
}