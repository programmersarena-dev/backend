<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class Problem extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'contest_id',
        'name',
        'tags',
        'time_limit',
        'memory_limit',
        'score',
        'description',
        'input',
        'output',
        'test_cases',
        'note',
    ];

    public function getTranslation($field = '', $language = false)
    {
        $language = $language == false ? app()->getLocale() : $language;
        $translations = $this->translations->where('language', $language)->first();
        return $translations != null ? $translations->$field : $this->$field;
    }

    public function translations()
    {
        return $this->hasMany(ProblemTranslation::class, 'problem_id', 'id');
    }

    public function char()
    {
        $problems = Problem::where('contest_id', $this->contest_id)->orderBy('id', 'asc')->get();
        foreach ($problems as $index => $problem) {
            if ($problem->id == $this->id) {
                return chr(ord('A') + $index);
            }
        }
        return 0;
    }

    public function contest()
    {
        return $this->belongsTo(Contest::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class, 'problem_id', 'id');
    }

    public function acceptableLanguages()
    {
        $languagesWithExtension = [];
        foreach (config('languages.dockerLanguages') as $language => $details) {
            foreach ($details['versions'] as $version) {
                $languagesWithExtension[] = $language . '-' . $version;
            }
        }
        return $languagesWithExtension;
    }

    public function solved($user = null)
    {
        if (!$user) $user = Auth::guard('sanctum')->user();
        if (!$user) return 0;

        $exists = $this->submissions()
            ->where('user_id', $user->id)
            ->where(function ($submission) {
                $submission->where('verdict', 'Accepted')
                    ->orWhere('verdict', '100');
            })
            ->exists();
        return $exists;
    }

    public function getAcceptedSubmissionsCountAttribute()
    {
        return $this->submissions()
            ->where('verdict', 'Accepted')
            ->orWhere('verdict', '100')
            ->select('user_id')
            ->distinct()
            ->count('user_id');
    }

    public function downloadAttachments()
    {
        $attachmentFolder = 'public/' . $this->test_cases . '/attachments';

        try {
            $files = Storage::disk('local')->files($attachmentFolder);

            if (empty($files)) {
                return response()->json(['message' => 'Goşundy tapylmady'], 404);
            }

            $filePath = $files[0];
            $filename = basename($filePath);

            $absolutePath = storage_path('app/' . $filePath);

            if (!file_exists($absolutePath)) {
                return response()->json(['message' => 'Faýl tapylmady'], 404);
            }

            return response()->download($absolutePath, $filename);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Goşundyny ýüklemekde ýalňyşlyk ýüze çykdy'], 500);
        }
    }
}
