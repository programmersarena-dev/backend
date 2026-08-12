<?php

namespace App\Http\Resources\User\Problem;

use App\Http\Resources\SubmissionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProblemDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $contestStatus = $this->contest?->getStatus();

        $tags = ($contestStatus === 'Ended' || !$this->contest_id)
            ? $this->whenLoaded('tags', fn() => $this->tags->pluck('name'))
            : [];

        $statementUrl = null;
        if ($this->contest?->hasAttachments() && $this->test_cases_path) {
            $statementPath = "public/{$this->test_cases_path}/statement.pdf";
            if (Storage::disk('local')->exists($statementPath)) {
                $statementUrl = route('problems.statement', $this->id);
            }
        }

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
            'statement_url' => $statementUrl,

            $this->mergeWhen(!$statementUrl, [
                'description' => $this->getTranslation('description') ?? '',
                'input' => $this->getTranslation('input') ?? '',
                'output' => $this->getTranslation('output') ?? '',
                'example_test_cases' => $this->resolveExampleTestCases(),
                'note' => $this->getTranslation('note') ?? '',
            ]),

            'user_submissions' => SubmissionResource::collection(
                $this->whenLoaded('userSubmissions')
            ),
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