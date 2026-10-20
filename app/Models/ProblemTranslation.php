<?php

namespace App\Models;

use App\Models\Concerns\SanitizesRichText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProblemTranslation extends Model
{
    use HasFactory;
    use SanitizesRichText;

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
