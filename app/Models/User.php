<?php

namespace App\Models;

use App\Notifications\CustomResetPasswordNotification;
use App\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Standard accepted status flags for online judge submissions.
     */
    public const ACCEPTED_STATUSES = ['Accepted', '100', 'AC'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'handle',
        'name',
        'email',
        'user_type',
        'password',
        'locale',
        'last_activity',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_activity' => 'datetime',
        'password' => 'hashed',
    ];

    public function sendEmailVerificationNotification(): void
    {
        if ($this->email_verified_at) {
            return;
        }
        $this->notify(new VerifyEmail());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomResetPasswordNotification($token));
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function contestRatings(): HasMany
    {
        return $this->hasMany(ContestRating::class);
    }

    /**
     * Accessor for accepted problems count.
     * Uses 'status' (not 'verdict') to match PostgreSQL schema.
     */
    public function getAcceptedProblemsCountAttribute(): int
    {
        if (array_key_exists('accepted_problems_count', $this->attributes)) {
            return (int) $this->attributes['accepted_problems_count'];
        }

        return $this->attributes['accepted_problems_count'] ??= $this->submissions()
            ->whereIn('status', self::ACCEPTED_STATUSES)
            ->distinct('problem_id')
            ->count('problem_id');
    }
}