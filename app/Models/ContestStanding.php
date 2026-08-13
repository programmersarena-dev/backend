<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContestStanding extends Model
{
    use HasFactory;

    /**
     * Disable default timestamps if using custom or single updated_at column.
     */
    public $timestamps = false;

    protected $fillable = [
        'contest_id',
        'result',
    ];

    /**
     * Automatic JSON casting for Eloquent.
     */
    protected $casts = [
        'result' => 'array',
    ];

    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';


    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }
}
