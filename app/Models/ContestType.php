<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContestType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * Contest types have many contests.
     */
    public function contests(): HasMany
    {
        return $this->hasMany(Contest::class, 'type_id');
    }
}