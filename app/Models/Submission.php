<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'problem_id',
        'language',
        'code',
        'verdict',
        'status',
        'outputs',
        'output',
        'time',
        'memory',
        'error_message',
        'judged_at',
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function problem()
    {
        return $this->belongsTo(Problem::class);
    }

    public function contest()
    {
        return $this->problem->contest();
    }

    public function time()
    {
        $time = '';
        if (!$this->contest->hasSubtasks() && $this->outputs) {
            $time = max(array_map(function ($output) {
                return $output->time;
            }, json_decode($this->outputs))) ?? 0;
        }
        return $time;
    }

    public function memory()
    {
        $memory = '';
        if (!$this->contest->hasSubtasks() && $this->outputs) {
            $memory = max(array_map(function ($output) {
                return $output->memory;
            }, json_decode($this->outputs))) ?? 0;
        }
        return $memory;
    }
}
