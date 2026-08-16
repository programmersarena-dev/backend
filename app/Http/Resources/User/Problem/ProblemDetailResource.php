<?php

namespace App\Http\Resources\User\Problem;

use App\Http\Resources\User\Submission\SubmissionListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProblemDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $contestStatus = $this->contest?->getStatus();

        $tags = ($contestStatus === 'Ended' || $this->contest_id)
            ? is_array($this->tags) ? $this->tags : json_decode($this->tags, true)
            : [];

        $statementUrl = null;
        if ($this->contest?->hasAttachments() && $this->test_cases_path) {
            $statementPath = "public/{$this->test_cases_path}/statement.pdf";
            if (Storage::disk('local')->exists($statementPath)) {
                $statementUrl = route('problem.statement', ['contest' => $this->contest_id, 'char' => $this->char]);
            }
        }

        $attachmentUrl = null;
        if ($this->contest?->hasAttachments() && $this->test_cases_path) {
            $attachmentPath = "public/{$this->test_cases_path}/attachments";
            $files = Storage::disk('local')->files($attachmentPath);

            if (!empty($files)) {
                $attachmentUrl = route('problem.attachments', ['contest' => $this->contest_id, 'char' => $this->char]);
            }
        }

        $submissions = auth()->id() 
            ? $this->submissions()
                ->where('user_id', auth()->id())
                ->latest()
                ->limit(5)
                ->get()
            : collect();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'slug' => $this->slug,
            'char' => $this->char,
            'name' => $this->getTranslation('name'),
            'time_limit' => $this->time_limit,
            'memory_limit' => $this->memory_limit,
            'tags' => $tags,
            'acceptable_languages' => $this->acceptableLanguages(),
            'attachment_url' => $attachmentUrl,
            'statement_url' => $statementUrl,

            $this->mergeWhen(!$statementUrl, [
                'description' => $this->getTranslation('description') ?? '',
                'input' => $this->getTranslation('input') ?? '',
                'output' => $this->getTranslation('output') ?? '',
                'example_test_cases' => $this->resolveExampleTestCases(),
                'note' => $this->getTranslation('note') ?? '',
            ]),

            'user_submissions' => SubmissionListResource::collection($submissions),
        ];
    }

    protected function resolveExampleTestCases(): array
    {
        if (!$this->test_cases_path) {
            return [];
        }

        $folder = "public/{$this->test_cases_path}";
        
        if (!Storage::disk('local')->exists($folder)) {
            return [];
        }

        $allFiles = Storage::disk('local')->files($folder);
        $inputFiles = preg_grep('/\/0[^\/]*\.in$/', $allFiles);

        $exampleTestCases = [];

        foreach ($inputFiles as $inputFile) {
            $outputFile = preg_replace('/\.in$/', '.out', $inputFile);

            if (in_array($outputFile, $allFiles, true)) {
                $exampleTestCases[] = [
                    'input' => rtrim(Storage::disk('local')->get($inputFile)),
                    'output' => rtrim(Storage::disk('local')->get($outputFile)),
                ];
            }
        }

        return $exampleTestCases;
    }
}