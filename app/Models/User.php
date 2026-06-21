<?php

namespace App\Models;

use App\Notifications\CustomResetPasswordNotification;
use App\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'user_type',
        'name',
        'email',
        'password',
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
        'password' => 'hashed',
    ];

    public function sendEmailVerificationNotification()
    {
        if ($this->email_verified_at)
            return;
        return $this->notify(new VerifyEmail());
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPasswordNotification($token));
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function rating()
    {
        return $this->hasOne(Rating::class);
    }

    public function getAcceptedProblemsCountAttribute()
    {
        return $this->submissions()
            ->where(function ($query) {
                $query->where('verdict', 'Accepted')
                      ->orWhere('verdict', '100');
            })
            ->select('problem_id')
            ->distinct()
            ->count('problem_id');
    }
}
