<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProblemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contestStatus = $this->contest?->getStatus();
        $testCasesFolder = $this->test_cases_path ? "public/{$this->test_cases_path}" : null;

        $submissions = [];
        if ($user = Auth::guard('sanctum')->user()) {
            $submissions = SubmissionResource::collection(
                $this->submissions()
                    ->where('user_id', $user->id)
                    ->orderBy('id', 'desc')
                    ->take(5)
                    ->get()
            );
        }

        $tags = ($contestStatus === 'ended') ? ($this->tags ?? []) : [];

        $response = [
            'contest' => [
                'name' => $this->contest?->name,
                'start_date' => $this->contest?->start_date,
                'end_date' => $this->contest?->end_date,
                'status' => $contestStatus,
            ],
            'name' => $this->getTranslation('name'),
            'time_limit' => $this->time_limit,
            'memory_limit' => $this->memory_limit,
            'submissions' => $submissions,
            'tags' => $tags,
            'acceptableLanguages' => $this->acceptableLanguages(),
        ];

        if ($this->contest?->hasAttachments()) {
            $statementPath = "{$testCasesFolder}/statement.pdf";

            $response['statement'] = ($testCasesFolder && Storage::disk('local')->exists($statementPath))
                ? base64_encode(Storage::disk('local')->get($statementPath))
                : null;

            return $response;
        }

        $exampleTestCases = $this->resolveExampleTestCases($testCasesFolder);

        $response['description'] = $this->getTranslation('description') ?? '';
        $response['input'] = $this->getTranslation('input') ?? '';
        $response['output'] = $this->getTranslation('output') ?? '';
        $response['example_test_cases'] = !empty($exampleTestCases) ? $exampleTestCases : null;
        $response['note'] = $this->getTranslation('note') ?? '';

        return $response;
    }

    /**
     * Efficiently scan and pair example test cases (01.in / 01.out, etc.)
     */
    protected function resolveExampleTestCases(?string $folder): array
    {
        if (!$folder || !Storage::disk('local')->exists($folder)) {
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
