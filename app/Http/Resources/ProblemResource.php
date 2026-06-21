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
        $filePath = 'public/' . $this->test_cases;

        $allFiles = Storage::files($filePath);

        $inputFiles = preg_grep('/^' . preg_quote($filePath, '/') . '\/0.*\.in$/', $allFiles);
        $outputFiles = preg_grep('/^' . preg_quote($filePath, '/') . '\/0.*\.out$/', $allFiles);

        $exampleTestCases = [];

        foreach ($inputFiles as $inputFile) {
            $outputFile = str_replace('.in', '.out', $inputFile);

            if (in_array($outputFile, $outputFiles)) {
                $exampleTestCase = [
                    'input' => rtrim(Storage::get($inputFile)),
                    'output' => rtrim(Storage::get($outputFile))
                ];
                $exampleTestCases[] = $exampleTestCase;
            }
        }

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

        $tags = $this->contest->getStatus() == 'ended' ? json_decode($this->tags) : [];

        if ($this->contest->hasAttachments()) {
            $statementPath = "public/{$this->test_cases}/statement.pdf";
            $statement = Storage::exists($statementPath) ? base64_encode(Storage::get($statementPath)) : null;
            return [
                'contest' => [
                    'name' => $this->contest->name,
                    'start_date' => $this->contest->start_date,
                    'end_date' => $this->contest->end_date,
                    'status' => $this->contest->getStatus(),
                ],
                'name' => $this->getTranslation("name"),
                'time_limit' => $this->time_limit,
                'memory_limit' => $this->memory_limit,
                'statement' => $statement,
                'submissions' => $submissions,
                'tags' => $tags,
                'acceptableLanguages' => $this->acceptableLanguages(),
            ];
        }

        return [
            'contest' => [
                'name' => $this->contest->name,
                'start_date' => $this->contest->start_date,
                'end_date' => $this->contest->end_date,
                'status' => $this->contest->getStatus(),
            ],
            'name' => $this->getTranslation("name"),
            'time_limit' => $this->time_limit,
            'memory_limit' => $this->memory_limit,
            'description' => $this->getTranslation("description") ?? '',
            'input' => $this->getTranslation("input") ?? '',
            'output' => $this->getTranslation("output") ?? '',
            'example_test_cases' => $exampleTestCases ? $exampleTestCases : null,
            'note' => $this->getTranslation("note") ?? '',
            'submissions' => $submissions,
            'tags' => $tags ?? '',
            'acceptableLanguages' => $this->acceptableLanguages(),
        ];
    }
}
