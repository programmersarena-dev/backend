<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProblemRequest;
use App\Http\Requests\UpdateProblemRequest;
use App\Http\Resources\ProblemListResource;
use App\Models\Contest;
use App\Models\Problem;
use App\Models\ProblemTranslation;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ProblemController extends Controller
{
    public function index(Contest $contest)
    {
        return ProblemListResource::collection(
            Problem::query()
                ->where('contest_id', $contest->id)
                ->orderBy('id', 'asc')
                ->get()
        );
    }

    public function store(Contest $contest, StoreProblemRequest $request)
    {
        if (!$contest->isEnded())
            return response()->json(['message' => 'Contest ended'], 404);
        $data = $request->validated();

        $file = $request->file('test_cases');
        $filename = time() . '-' . $file->getClientOriginalName();
        $filePath = $file->storeAs('test_cases', $filename, 'public');

        $zip = new ZipArchive;
        $zipPath = storage_path('app/public/' . $filePath);
        $extractPath = storage_path('app/public/test_cases/' . pathinfo($filename, PATHINFO_FILENAME));

        if ($zip->open($zipPath) === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();
            unlink($zipPath);
        } else {
            return response()->json(['message' => 'Zip faýly açmakda ýalňyşlyk ýüze çykdy'], 500);
        }

        $problem = Problem::create([
            'contest_id' => $contest->id,
            'name' => $data['name'],
            'tags' => $data['tags'] ?? '',
            'time_limit' => $data['time_limit'],
            'memory_limit' => $data['memory_limit'],
            'score' => $data['score'],
            'description' => $data['description'] ?? '',
            'input' => $data['input'] ?? '',
            'output' => $data['output'] ?? '',
            'test_cases' => 'test_cases/' . pathinfo($filename, PATHINFO_FILENAME),
            'note' => $data['note'] ?? '',
        ]);

        $problemTranslationEN = ProblemTranslation::create([
            'problem_id' => $problem->id,
            'language' => 'en',
            'name' => $data['name_en'],
            'description' => $data['description_en'],
            'input' => $data['input_en'] ?? '',
            'output' => $data['output_en'] ?? '',
            'note' => $data['note_en'] ?? '',
        ]);

        $problemTranslationRU = ProblemTranslation::create([
            'problem_id' => $problem->id,
            'language' => 'ru',
            'name' => $data['name_ru'],
            'description' => $data['description_ru'],
            'input' => $data['input_ru'] ?? '',
            'output' => $data['output_ru'] ?? '',
            'note' => $data['note_ru'] ?? '',
        ]);

        return response()->json(['message' => 'Mesele üstünlikli döredildi'], 202);
    }

    public function edit(Contest $contest, $char)
    {
        $problem = $contest->problems()->orderBy('id', 'asc')->skip(ord($char) - ord('A'))->first();

        $problem->tags = json_decode($problem->tags);
        $problem->test_cases = '';
        return [
            'id' => $problem->id,
            'contest_id' => $problem->contest_id,
            'name' => $problem->name,
            'tags' => $problem->tags,
            'time_limit' => $problem->time_limit,
            'memory_limit' => $problem->memory_limit,
            'score' => $problem->score,
            'description' => $problem->description,
            'input' => $problem->input,
            'output' => $problem->output,
            'test_cases' => $problem->test_cases,
            'note' => $problem->note,

            "name_en" => $problem->getTranslation("name","en"),
            "description_en" => $problem->getTranslation("description","en"),
            "input_en" => $problem->getTranslation("input","en"),
            "output_en" => $problem->getTranslation("output","en"),
            "note_en" => $problem->getTranslation("note","en"),
            "name_ru" => $problem->getTranslation("name","ru"),
            "description_ru" => $problem->getTranslation("description","ru"),
            "input_ru" => $problem->getTranslation("input","ru"),
            "output_ru" => $problem->getTranslation("output","ru"),
            "note_ru" => $problem->getTranslation("note","ru"),
        ];
    }

    public function update(Contest $contest, $char, UpdateProblemRequest $request)
    {
        if (!$contest->isEnded())
            return response()->json(['error' => 'Contest ended'], 404);
        $problem = $contest->problems()->orderBy('id', 'asc')->skip(ord($char) - ord('A'))->first();

        $data = $request->validated();

        if ($request->hasFile('test_cases')) {
            $oldExtractPath = storage_path('app/public/' . $problem->test_cases);
            if (is_dir($oldExtractPath)) {
                Storage::disk('public')->deleteDirectory($problem->test_cases);
            }

            $file = $request->file('test_cases');
            $filename = time() . '-' . $file->getClientOriginalName();
            $filePath = $file->storeAs('test_cases', $filename, 'public');

            $zip = new ZipArchive;
            $zipPath = storage_path('app/public/' . $filePath);
            $extractPath = storage_path('app/public/test_cases/' . pathinfo($filename, PATHINFO_FILENAME));

            if ($zip->open($zipPath) === TRUE) {
                $zip->extractTo($extractPath);
                $zip->close();
                unlink($zipPath);
            } else {
                return response()->json(['message' => 'Zip faýly açmakda ýalňyşlyk ýüze çykdy'], 500);
            }

            $problem->test_cases = 'test_cases/' . pathinfo($filename, PATHINFO_FILENAME);
        }

        $problem->update([
            'name' => $data['name'],
            'tags' => $data['tags'] ?? '',
            'time_limit' => $data['time_limit'],
            'memory_limit' => $data['memory_limit'],
            'score' => $data['score'],
            'description' => $data['description'] ?? '',
            'input' => $data['input'] ?? '',
            'output' => $data['output'] ?? '',
            'note' => $data['note'] ?? '',
        ]);

        $problem_en = ProblemTranslation::where('problem_id',$problem->id)->where('language','en')->firstOrFail();
        $problem_en->update([
            'name' => $data['name_en'],
            'description' => $data['description_en'] ?? '',
            'input' => $data['input_en'] ?? '',
            'output' => $data['output_en'] ?? '',
            'note' => $data['note_en'] ?? '',
        ]);

        $problem_ru = ProblemTranslation::where('problem_id',$problem->id)->where('language','ru')->firstOrFail();
        $problem_ru->update([
            'name' => $data['name_ru'],
            'description' => $data['description_ru'] ?? '',
            'input' => $data['input_ru'] ?? '',
            'output' => $data['output_ru'] ?? '',
            'note' => $data['note_ru'] ?? '',
        ]);

        return response()->json(['message' => 'Mesele üstünlikli üýtgedildi'], 200);
    }

    public function destroy(Contest $contest, $char)
    {
        if (!$contest->isEnded())
            return response()->json(['message' => 'Contest ended'], 404);
        $problem = $contest->problems()->orderBy('id', 'asc')->skip(ord($char) - ord('A'))->first();
        $problem_en = ProblemTranslation::where('problem_id',$problem->id)->where('language','en')->firstOrFail();
        $problem_ru = ProblemTranslation::where('problem_id',$problem->id)->where('language','ru')->firstOrFail();

        $extractPath = storage_path('app/public/' . $problem->test_cases);
        if (is_dir($extractPath)) {
            Storage::disk('public')->deleteDirectory($problem->test_cases);
        }

        $problem->delete();
        $problem_en->delete();
        $problem_ru->delete();

        return response()->json(['message' => 'Mesele üstünlikli pozuldy'], 200);
    }

    public function downloadTestCases(Contest $contest, $char)
    {
        $problem = $contest->problems()->orderBy('id', 'asc')->skip(ord($char) - ord('A'))->first();

        $testCasesPath = storage_path('app/public/' . $problem->test_cases);
        $zipFileName = $problem->name . '-test-cases.zip';
        $zipFilePath = storage_path('app/public/' . $zipFileName);

        $zip = new ZipArchive;
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($testCasesPath));
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($testCasesPath) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
        } else {
            return response()->json(['message' => 'Zip ýasamakda ýalňyşlyk ýüze çykdy'], 500);
        }

        return response()->download($zipFilePath)->deleteFileAfterSend(true);
    }
}
