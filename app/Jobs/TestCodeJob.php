<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Contest;
use App\Models\Standing;
use Illuminate\Support\Facades\File;
use Exception;
use Throwable;

class TestCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $host;
    protected $problem;
    protected $submission;
    protected $type;
    protected $language;
    protected $version;

    /**
     * Create a new job instance.
     */
    public function __construct($host, $submission, $language, $version)
    {
        $this->host = $host;
        $this->submission = $submission;
        $this->problem = $submission->problem;
        $this->type = $this->problem->contest->type;
        $this->language = $language;
        $this->version = $version;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->type == 'IOI')
            $this->executeCodeGrader();
        else
            $this->executeCodeDocker();
        $this->host->cleanupTempDir();
    }

    public function failed(Throwable $exception)
    {
        $maxLength = 255;
        $message = 'Failed: ' . $exception->getMessage();

        if (strlen($message) > $maxLength) {
            $message = substr($message, 0, $maxLength - 3) . '...';
        }

        $this->submission->update([
            'verdict' => $message,
        ]);
    }

    protected function executeCodeDocker()
    {
        try {
            $docker = new \App\Services\CodeService(
                $this->host,
                $this->language,
                $this->version,
                $this->problem->time_limit,
                $this->problem->memory_limit
            );

            if (!empty($this->language['commandCompile'])) {
                try {
                    $returnVar = $docker->compile();
                } catch (\Exception $e) {
                    $this->submission->update([
                        'verdict' => 'CE',
                    ]);
                    return;
                }
                if ($returnVar !== 0) {
                    $this->submission->update([
                        'verdict' => 'CE',
                    ]);
                    return;
                }
            }

            $testCasesPath = storage_path('app/public/' . $this->problem->test_cases);
            $results = [];

            $testCases = glob("$testCasesPath/*.in");
            natsort($testCases);
            foreach ($testCases as $index => $testCaseFile) {
                $input = file_get_contents($testCaseFile);
                $input = str_replace("\r\n", "\n", $input);
                $input = trim($input, " \n\r");

                $expectedOutputFile = str_replace('.in', '.out', $testCaseFile);
                $expectedOutput = file_exists($expectedOutputFile) ? file_get_contents($expectedOutputFile) : '';
                $expectedOutput = str_replace("\r\n", "\n", $expectedOutput);
                $expectedOutput = trim($expectedOutput, " \n\r");

                try {
                    $this->host->saveText($input, 'input.txt');
                } catch (\Exception $e) {
                    $this->submission->update([
                        'verdict' => 'Error, ' . substr($e->getMessage(), 0, 200),
                    ]);
                    return;
                }

                try {
                    $result = $docker->execute($input, $expectedOutput);
                } catch (\Exception $e) {
                    $this->submission->update([
                        'verdict' => 'Error, ' . substr($e->getMessage(), 0, 200),
                    ]);
                    return;
                }
                $results[] = $result;
                $this->submission->update([
                    'outputs' => $results,
                    'verdict' => 'Compiling-' . $index + 1,
                ]);

                if ($result['log'] != 'OK') {
                    break;
                }
            }

            $finalVerdict = end($results)['log'] == 'OK' ? 'Accepted' : end($results)['log'] . '-' . sizeof($results);

            $solved = $this->problem->solved($this->submission->user);

            $this->submission->update([
                'outputs' => json_encode($results),
                'verdict' => $finalVerdict,
            ]);

            $contest = $this->problem->contest;
            if ($contest->getStatus() === 'started' && !$solved) {
                $this->calcContestStandings(
                    $contest->id,
                    ord($this->problem->char()) - ord('A'),
                    $this->problem->score,
                    $this->submission->user->name,
                    $this->submission->created_at->diffInSeconds($contest->start_date),
                    $this->submission->verdict,
                );
            }
        } catch (\Exception $e) {
            $this->submission->update([
                'verdict' => 'Error, ' . substr($e->getMessage(), 0, 200),
            ]);
        }
    }

    protected function executeCodeGrader()
    {
        try {
            $docker = new \App\Services\CodeService(
                $this->host,
                $this->language,
                $this->version,
                $this->problem->time_limit,
                $this->problem->memory_limit
            );

            $problemFolder = storage_path('app/public/' . $this->problem->test_cases);

            $graderPath = "{$problemFolder}/graders";
            $graderFiles = File::files($graderPath);

            foreach ($graderFiles as $graderFile) {
                $this->host->saveFile($graderFile, $graderFile->getFilename());
            }

            $docker->compile(true);

            $subTasks = [];
            $points = json_decode(file_get_contents("{$problemFolder}/points.json"), true);
            $countPoints = [];

            foreach ($points as $index => $point) {
                $this->submission->update([
                    'verdict' => 'Compiling-T#' . $index + 1,
                ]);

                $testCases = glob("$problemFolder/tests/{$index}*.in");
                natsort($testCases);
                $subTaskResults = [];

                foreach ($testCases as $testCaseFile) {
                    $input = file_get_contents($testCaseFile);
                    $expectedOutputFile = str_replace('.in', '.out', $testCaseFile);
                    $expectedOutput = file_exists($expectedOutputFile) ? file_get_contents($expectedOutputFile) : '';

                    $this->host->saveText($input, 'input.txt');

                    try {
                        $subTaskResults[] = $docker->execute($input, $expectedOutput);
                    } catch (\Exception $e) {
                        $this->submission->update([
                            'verdict' => 'Error, ' . substr($e->getMessage(), 0, 200),
                        ]);
                        return;
                    }
                }

                $subTaskPoints = collect($subTaskResults)->pluck('log')->every(fn($log) => $log === 'OK') ? $point : 0;
                $countPoints[] = $subTaskPoints;

                $subTasks[] = [
                    'subTaskResults' => $subTaskResults,
                    'point' => intval($subTaskPoints),
                ];
            }

            $solved = $this->problem->solved($this->submission->user);

            $this->submission->update([
                'outputs' => json_encode($subTasks),
                'verdict' => array_sum($countPoints),
            ]);

            $contest = $this->problem->contest;
            if ($contest->getStatus() === 'started' && !$solved) {
                $this->calcContestStandings(
                    $contest->id,
                    ord($this->problem->char()) - ord('A'),
                    $this->problem->score,
                    $this->submission->user->name,
                    $this->submission->created_at->diffInSeconds($contest->start_date),
                    $countPoints,
                );
            }
        } catch (\Exception $e) {
            $this->submission->update([
                'verdict' => 'Error, ' . substr($e->getMessage(), 0, 200),
            ]);
        }
    }

    private function calcContestStandings($contestId, $problemId, $problemScore, $userName, $diffInSeconds, $verdict)
    {
        $contest = Contest::find($contestId);
        if (!$contest) {
            return response()->json(['message' => 'Bäsleşik tapylmady'], 404);
        }

        $standings = Standing::firstOrCreate(['contest_id' => $contestId]);
        if ($contest->type->name === 'Classic' || $contest->type->name === 'IOI' || $contest->type->name === 'ICPC')
            $result = $standings->addUserStanding([$userName], $contest->type->name);
        elseif ($contest->type->name === 'Duel')
            $result = $standings->addUserStanding([$userName, $contest->getComponent($userName)], $contest->type->name);

        if ($contest->hasSubtasks()) {
            $result = $this->updateSubtaskScores($result, $contest->type->name, $userName, $problemId, $verdict);
        } else {
            $score = ($verdict === 'Accepted') ? $problemScore - intdiv($diffInSeconds, 60) : -50;
            $result = $this->updateRegularScores(
                $result,
                $contest->type->name,
                $userName,
                $problemId,
                $score,
                $diffInSeconds,
            );
        }

        $sortedResults = $result->sortByDesc('total_score')->values()->toArray();
        $standings->update([
            'result' => json_encode($result),
        ]);

        return response()->json($sortedResults);
    }

    private function updateRegularScores($result, $contestType, $userName, $problemId, $score, $diffInSeconds)
    {
        return collect($result)->map(function ($item) use ($userName, $problemId, $score, $contestType, $diffInSeconds) {
            if ($item['username'] === $userName) {
                if ($contestType !== 'Duel' || ($contestType === 'Duel' && $item['problems2'][$problemId]['score'] <= 0)) {
                    $item['problems'][$problemId]['score'] += $score;
                    if ($score > 0)
                        $item['problems'][$problemId] = [
                            'score' => $item['problems'][$problemId]['score'],
                            'accepted_at' => gmdate('H:i:s', $diffInSeconds),
                        ];
                    $item['total_score'] += $score;
                }
            } elseif ($contestType === 'Duel' && $item['username2'] === $userName) {
                if ($contestType !== 'Duel' || ($contestType === 'Duel' && $item['problems'][$problemId]['score'] <= 0)) {
                    $item['problems2'][$problemId]['score'] += $score;
                    if ($score > 0)
                        $item['problems2'][$problemId] = [
                            'score' => $item['problems2'][$problemId]['score'],
                            'accepted_at' => gmdate('H:i:s', $diffInSeconds),
                        ];
                    $item['total_score2'] += $score;
                }
            }
            if ($contestType === 'Duel') {
                if ($item['total_score2'] > $item['total_score']) {
                    [$item['username'], $item['username2']] = [$item['username2'], $item['username']];
                    [$item['problems'], $item['problems2']] = [$item['problems2'], $item['problems']];
                    [$item['total_score'], $item['total_score2']] = [$item['total_score2'], $item['total_score']];
                }
            }
            return $item;
        });
    }

    private function updateSubtaskScores($result, $contestType, $userName, $problemId, $points)
    {
        return collect($result)->map(function ($item) use ($userName, $problemId, $points, $contestType) {
            if ($item['username'] === $userName) {
                if (!is_array($item['problems'][$problemId] ?? null)) {
                    $item['problems'][$problemId] = array_fill(0, count($points), 0);
                }
                if (count($item['problems'][$problemId]) < count($points)) {
                    $item['problems'][$problemId] = array_fill(0, count($points), 0);
                }
                foreach ($points as $index => $point) {
                    if ($contestType !== 'Duel' || ($contestType === 'Duel' && max($item['problems'][$problemId][$index], $point) > $item['problems2'][$problemId][$index]))
                        $item['problems'][$problemId][$index] = max($item['problems'][$problemId][$index], $point);
                }
                $item['total_score'] = $this->calculateTotalScore($item);
            } elseif ($contestType === 'Duel' && $item['username2'] === $userName) {
                if (!is_array($item['problems2'][$problemId] ?? null)) {
                    $item['problems2'][$problemId] = array_fill(0, count($points), 0);
                }
                if (count($item['problems2'][$problemId]) < count($points)) {
                    $item['problems2'][$problemId] = array_fill(0, count($points), 0);
                }
                foreach ($points as $index => $point) {
                    if ($contestType !== 'Duel' || ($contestType === 'Duel' && max($item['problems'][$problemId][$index], $point) > $item['problems2'][$problemId][$index]))
                        $item['problems2'][$problemId][$index] = max($item['problems2'][$problemId][$index], $point);
                }
                $item['total_score2'] = $this->calculateTotalScore($item);
            }
            return $item;
        });
    }

    private function calculateTotalScore($item)
    {
        return array_sum(array_map('array_sum', $item['problems']));
    }
}
