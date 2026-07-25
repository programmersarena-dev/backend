<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProblemTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'problem_id',
        'language',
        'name',
        'description',
        'input',
        'output',
        'note',
    ];

    public function problem()
    {
        return $this->belongsTo(Problem::class);
    }
}
