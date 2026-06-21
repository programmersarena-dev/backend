<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'current_rating',
        'contest_ratings',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
