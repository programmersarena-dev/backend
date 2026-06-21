<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContestType extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
    ];

    public function contest()
    {
        return $this->hasMany(Contest::class, 'id', 'type_id');
    }
}
