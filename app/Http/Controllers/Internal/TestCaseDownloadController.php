<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Problem;
use Illuminate\Http\Request;
use ZipArchive;

class TestCaseDownloadController extends Controller
{
    public function download(Request $request, Problem $problem)
    {
        $extractPath = storage_path('app/public/' . $problem->test_cases_path);
        abort_unless(is_dir($extractPath), 404);

        $zipFileName = "{$problem->id}-{$problem->test_cases_path}.zip";
        $zipPath = storage_path('app/tmp/' . str_replace('/', '_', $zipFileName));

        if (!is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return response()->json(['message' => 'Failed to build test case archive'], 500);
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractPath));
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($extractPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}