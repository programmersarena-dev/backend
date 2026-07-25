<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class Problem extends Model
{
    use HasFactory;

    protected $fillable = [
        'contest_id',
        'code',
        'slug',
        'name',
        'tags',
        'time_limit',
        'memory_limit',
        'difficulty',
        'score',
        'description',
        'input',
        'output',
        'test_cases_path',
        'note',
        'is_public',
    ];

    /**
     * Automatic type casting for attributes.
     */
    protected $casts = [
        'tags' => 'array',
        'time_limit' => 'integer',
        'memory_limit' => 'integer',
        'score' => 'integer',
        'difficulty' => 'integer',
        'is_public' => 'boolean',
    ];

    /* -------------------------------------------------------------------------- */
    /* RELATIONSHIPS                                                              */
    /* -------------------------------------------------------------------------- */

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProblemTranslation::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /* -------------------------------------------------------------------------- */
    /* HELPER & OPTIMIZED METHODS                                                 */
    /* -------------------------------------------------------------------------- */

    /**
     * Get field translation with fallback to default problem attribute.
     * Uses loaded relation if available to avoid firing extra SQL queries.
     */
    public function getTranslation(string $field = 'name', ?string $language = null): mixed
    {
        $language = $language ?? app()->getLocale();

        // If relation is eager-loaded, search in collection; otherwise execute query
        $translation = $this->relationLoaded('translations')
            ? $this->translations->firstWhere('language', $language)
            : $this->translations()->where('language', $language)->first();

        return $translation?->{$field} ?? $this->{$field};
    }

    /**
     * Calculate problem letter identifier ('A', 'B', 'C'...) inside a contest.
     */
    public function char(): string
    {
        if (!$this->contest_id) {
            return $this->code ?? (string) $this->id;
        }

        // Count how many problems exist in this contest with a lower ID
        $index = static::where('contest_id', $this->contest_id)
            ->where('id', '<', $this->id)
            ->count();

        return chr(ord('A') + $index);
    }

    /**
     * Return acceptable language versions defined in system config.
     */
    public function acceptableLanguages(): array
    {
        $languages = [];
        $dockerLanguages = config('languages.dockerLanguages', []);

        foreach ($dockerLanguages as $language => $details) {
            foreach ($details['versions'] ?? [] as $version) {
                $languages[] = "{$language}-{$version}";
            }
        }

        return $languages;
    }

    /**
     * Check whether the contest associated with this problem has ended.
     */
    public function isContestEnded(): bool
    {
        if (!$this->contest_id) {
            return true; // Non-contest / public archive problems
        }

        $contest = $this->relationLoaded('contest')
            ? $this->contest
            : $this->contest()->first();

        return $contest ? $contest->getStatus() === 'ended' : false;
    }

    /**
     * Check if a specific user (or authenticated user) has solved this problem.
     * Updated: fixed column from 'verdict' to 'status'.
     */
    public function isSolvedBy($user = null): bool
    {
        $user = $user ?? Auth::guard('sanctum')->user();

        if (!$user) {
            return false;
        }

        return $this->submissions()
            ->where('user_id', $user->id)
            ->whereIn('status', Submission::ACCEPTED_STATUSES ?? ['Accepted', '100'])
            ->exists();
    }

    /* -------------------------------------------------------------------------- */
    /* ACCESSORS                                                                  */
    /* -------------------------------------------------------------------------- */

    /**
     * Accessor for unique solved user count.
     * Updated: fixed column from 'verdict' to 'status'.
     */
    public function getAcceptedProblemsCountAttribute(): int
    {
        if (array_key_exists('accepted_problems_count', $this->attributes)) {
            return (int) $this->attributes['accepted_problems_count'];
        }

        return $this->attributes['accepted_problems_count'] = $this->submissions()
            ->whereIn('status', ['Accepted', '100'])
            ->distinct('problem_id')
            ->count('problem_id');
    }

    /* -------------------------------------------------------------------------- */
    /* FILE UTILITIES                                                             */
    /* -------------------------------------------------------------------------- */

    /**
     * Resolves absolute file path for downloading problem attachments.
     */
    public function getAttachmentPath(): ?string
    {
        if (!$this->test_cases_path) {
            return null;
        }

        $folder = "public/{$this->test_cases_path}/attachments";
        $files = Storage::disk('local')->files($folder);

        if (empty($files)) {
            return null;
        }

        $relativePath = $files[0];
        $fullPath = storage_path("app/{$relativePath}");

        return file_exists($fullPath) ? $fullPath : null;
    }
}
