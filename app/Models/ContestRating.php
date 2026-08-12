<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContestRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contest_id',
        'rank',
        'solved',
        'old_rating',
        'new_rating',
    ];

    protected $casts = [
        'rank'       => 'integer',
        'solved'     => 'integer',
        'old_rating' => 'integer',
        'new_rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contest()
    {
        return $this->belongsTo(Contest::class);
    }

    public function getRatingChangeAttribute(): int
    {
        return $this->new_rating - $this->old_rating;
    }
}
