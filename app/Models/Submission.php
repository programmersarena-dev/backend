<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    use HasFactory;

    /* -------------------------------------------------------------------------- */
    /* SUBMISSION STATUS CONSTANTS                                                */
    /* -------------------------------------------------------------------------- */

    // Pending & Processing Statuses
    public const STATUS_PENDING = 'Pending';
    public const STATUS_IN_QUEUE = 'In Queue';
    public const STATUS_COMPILING = 'Compiling';
    public const STATUS_RUNNING = 'Running';
    public const STATUS_JUDGING = 'Judging';

    // Successful / Accepted Statuses
    public const STATUS_ACCEPTED = 'Accepted';
    public const STATUS_AC = 'AC';
    public const STATUS_100 = '100';

    // Wrong Answer / Partial
    public const STATUS_WRONG_ANSWER = 'Wrong Answer';
    public const STATUS_WA = 'WA';
    public const STATUS_PARTIAL_ACCEPT = 'Partial Accept';

    // Resource Limit Errors
    public const STATUS_TIME_LIMIT_EXCEEDED = 'Time Limit Exceeded';
    public const STATUS_TLE = 'TLE';
    public const STATUS_MEMORY_LIMIT_EXCEEDED = 'Memory Limit Exceeded';
    public const STATUS_MLE = 'MLE';
    public const STATUS_OUTPUT_LIMIT_EXCEEDED = 'Output Limit Exceeded';
    public const STATUS_OLE = 'OLE';

    // Compilation & Execution Errors
    public const STATUS_COMPILATION_ERROR = 'Compilation Error';
    public const STATUS_CE = 'CE';
    public const STATUS_RUNTIME_ERROR = 'Runtime Error';
    public const STATUS_RE = 'RE';

    // System / Judge Internal Errors
    public const STATUS_SYSTEM_ERROR = 'System Error';
    public const STATUS_JUDGE_ERROR = 'Judge Error';
    public const STATUS_RESTRICTED_FUNC = 'Restricted Function';
    public const STATUS_PRESENTATION_ERROR = 'Presentation Error';
    public const STATUS_PE = 'PE';

    /* -------------------------------------------------------------------------- */
    /* CATEGORIZED STATUS ARRAYS                                                 */
    /* -------------------------------------------------------------------------- */

    /**
     * Accepted status identifiers across various judge engine formats.
     */
    public const ACCEPTED_STATUSES = [
        self::STATUS_ACCEPTED,
        self::STATUS_AC,
        self::STATUS_100,
    ];

    /**
     * Pending or active judging statuses.
     */
    public const PENDING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_QUEUE,
        self::STATUS_COMPILING,
        self::STATUS_RUNNING,
        self::STATUS_JUDGING,
        '0',
    ];

    /**
     * Final failed submission verdicts.
     */
    public const REJECTED_STATUSES = [
        self::STATUS_WRONG_ANSWER,
        self::STATUS_WA,
        self::STATUS_TIME_LIMIT_EXCEEDED,
        self::STATUS_TLE,
        self::STATUS_MEMORY_LIMIT_EXCEEDED,
        self::STATUS_MLE,
        self::STATUS_OUTPUT_LIMIT_EXCEEDED,
        self::STATUS_OLE,
        self::STATUS_COMPILATION_ERROR,
        self::STATUS_CE,
        self::STATUS_RUNTIME_ERROR,
        self::STATUS_RE,
        self::STATUS_SYSTEM_ERROR,
        self::STATUS_JUDGE_ERROR,
        self::STATUS_RESTRICTED_FUNC,
        self::STATUS_PRESENTATION_ERROR,
        self::STATUS_PE,
    ];

    protected $fillable = [
        'user_id',
        'problem_id',
        'contest_id',
        'language',
        'code',
        'status',
        'outputs',
        'output',
        'time',
        'memory',
        'error_message',
        'judged_at',
    ];

    protected $casts = [
        'time' => 'integer',
        'memory' => 'integer',
        'judged_at' => 'datetime',
        'outputs' => 'array',
    ];

    /* -------------------------------------------------------------------------- */
    /* RELATIONSHIPS                                                              */
    /* -------------------------------------------------------------------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    /* -------------------------------------------------------------------------- */
    /* HELPER METHODS & ACCESSORS                                                 */
    /* -------------------------------------------------------------------------- */

    /**
     * Determine if submission is accepted.
     */
    public function isAccepted(): bool
    {
        return in_array($this->status, self::ACCEPTED_STATUSES, true);
    }

    /**
     * Determine if submission is currently being compiled, run, or judged.
     */
    public function isPending(): bool
    {
        if (in_array($this->status, self::PENDING_STATUSES, true)) {
            return true;
        }

        // Catch dynamic status strings like "Compiling Test #1" or "Running on test 3"
        return str_starts_with($this->status ?? '', 'Compiling') ||
            str_starts_with($this->status ?? '', 'Running') ||
            str_starts_with($this->status ?? '', 'Judging');
    }

    /* -------------------------------------------------------------------------- */
    /* QUERY SCOPES                                                               */
    /* -------------------------------------------------------------------------- */

    /**
     * Scope query to only include accepted submissions.
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACCEPTED_STATUSES);
    }

    /**
     * Scope query to only include active/pending submissions.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', self::PENDING_STATUSES)
            ->orWhere('status', 'LIKE', 'Compiling%')
            ->orWhere('status', 'LIKE', 'Running%')
            ->orWhere('status', 'LIKE', 'Judging%');
    }

    /**
     * Scope query to only include completed / finished judgements.
     */
    public function scopeJudged(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::PENDING_STATUSES)
            ->where('status', 'NOT LIKE', 'Compiling%')
            ->where('status', 'NOT LIKE', 'Running%')
            ->where('status', 'NOT LIKE', 'Judging%');
    }
}
