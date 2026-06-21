<?php

namespace App\Services;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class LocalHostService
{
    private $tempDir;

    public function __construct()
    {
        $baseDir = storage_path('app/tmp');
        $this->tempDir = $baseDir . '/' . uniqid();

        if (!File::exists($baseDir)) {
            File::makeDirectory($baseDir, 0755, true);
        }

        if (!File::exists($this->tempDir)) {
            File::makeDirectory($this->tempDir, 0755, true);
        }
    }

    public function getTempDir()
    {
        return $this->tempDir;
    }

    public function saveText($text, $path)
    {
        if (preg_match('/[^a-zA-Z0-9_\-.\/]/', $path)) {
            throw new \InvalidArgumentException("Invalid path: {$path}");
        }

        $tempFileName = uniqid('input_', true) . '.txt';
        $tempFilePath = $this->tempDir . "/{$tempFileName}";

        File::put($tempFilePath, $text);

        $finalPath = $this->tempDir . "/{$path}";

        if (!File::move($tempFilePath, $finalPath)) {
            throw new \RuntimeException("Failed to move file to path: {$finalPath}");
        }
    }

    public function saveFile($file, $path)
    {
        $tempFilePath = $file->getRealPath();
        $destinationPath = $this->tempDir . '/' . $path;

        if (!File::copy($tempFilePath, $destinationPath)) {
            throw new \RuntimeException("Failed to move file to local temp directory.");
        }
    }

    public function moveFile($sourcePath, $path)
    {
        if (!file_exists($sourcePath)) {
            throw new \RuntimeException("Source file does not exist: {$sourcePath}");
        }

        $destinationPath = $this->tempDir . '/' . $path;

        if (!File::copy($sourcePath, $destinationPath)) {
            throw new \RuntimeException("Failed to move file.");
        }
    }

    public function executeCommand($command)
    {
        exec($command, $output, $returnVar);
        return [$output, $returnVar];
    }

    public function cleanupTempDir()
    {
        if (File::exists($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
    }
}
