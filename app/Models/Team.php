<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot('status', 'responded_at')
            ->withTimestamps();
    }

    public function acceptedMembers()
    {
        return $this->members()->wherePivot('status', 'accepted');
    }

    public function pendingMembers()
    {
        return $this->members()->wherePivot('status', 'pending');
    }
}
