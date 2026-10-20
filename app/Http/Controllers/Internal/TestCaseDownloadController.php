<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Problem;
use App\Support\TestCaseBundle;
use Illuminate\Http\Request;
use ZipArchive;

class TestCaseDownloadController extends Controller
{
    public function download(Request $request, Problem $problem)
    {
        abort_if($request->filled('version') && $request->query('version') !== TestCaseBundle::version($problem),
            409, 'Test cases changed after this submission was queued. Rejudge the submission.');
        $extractPath = storage_path('app/public/'.$problem->test_cases_path);
        abort_unless(is_dir($extractPath), 404);

        $temporaryDirectory = storage_path('app/tmp');
        if (! is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0755, true);
        }
        $zipPath = tempnam($temporaryDirectory, 'tests-');

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Failed to build test case archive'], 500);
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractPath));
        foreach ($files as $file) {
            if (! $file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($extractPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
