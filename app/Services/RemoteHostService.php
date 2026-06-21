<?php

namespace App\Services;
use Illuminate\Support\Facades\Storage;

class RemoteHostService
{
    private $remoteHost;
    private $tempDir;

    public function __construct()
    {
        $this->remoteHost = config('judge.servers.server_1.user') . '@' . config('judge.servers.server_1.ip');
        $baseDir = config('judge.servers.server_1.tmp');
        $this->tempDir = $baseDir . '/tmp/' . uniqid();

        $sshCommandCreateDir = "ssh {$this->remoteHost} \"mkdir -p {$this->tempDir}\"";
        exec($sshCommandCreateDir, $output, $returnVar);
        if ($returnVar !== 0) {
            throw new \RuntimeException(sprintf('Remote directory "%s" could not be created.', $this->tempDir));
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
        $tempFilePath = storage_path("app/tmp/{$tempFileName}");

        if (!Storage::exists('tmp')) {
            Storage::makeDirectory('tmp');
        }

        Storage::put("tmp/{$tempFileName}", $text);

        $sshCommandWrite = "scp {$tempFilePath} {$this->remoteHost}:{$this->tempDir}/{$path}";

        exec($sshCommandWrite, $output, $returnVar);

        Storage::delete("tmp/{$tempFileName}");
        if ($returnVar !== 0) {
            throw new \RuntimeException("Error writing text to file on remote server");
        }
    }

    public function saveFile($file, $path)
    {
        $tempStream = fopen($file->getRealPath(), 'rb');
        $scpCommand = "scp /dev/stdin {$this->remoteHost}:{$this->tempDir}/{$path}";

        $process = proc_open($scpCommand, [
            0 => $tempStream, // File as input
            1 => ['pipe', 'w'], // Standard output
            2 => ['pipe', 'w']  // Standard error
        ], $pipes);

        if (is_resource($process)) {
            fclose($tempStream);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $returnCode = proc_close($process);

            if ($returnCode !== 0) {
                throw new \RuntimeException("Failed to transfer file to remote server. Error: {$stderr}");
            }
        } else {
            throw new \RuntimeException('Failed to initiate SCP process.');
        }
    }

    public function moveFile(string $sourcePath, string $path): void
    {
        if (!file_exists($sourcePath)) {
            throw new \RuntimeException("Source file does not exist: {$sourcePath}");
        }

        if (preg_match('/[^a-zA-Z0-9_\-.]/', $path)) {
            throw new \InvalidArgumentException("Invalid path: {$path}");
        }

        $scpCommand = sprintf(
            'scp -q -B /dev/stdin %s:%s',
            escapeshellarg($this->remoteHost),
            escapeshellarg($this->tempDir . '/' . $path)
        );

        $stream = fopen($sourcePath, 'rb');

        $process = proc_open($scpCommand, [
            0 => $stream,
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ], $pipes);

        if (!is_resource($process)) {
            fclose($stream);
            throw new \RuntimeException('Failed to start SCP process');
        }

        fclose($stream);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new \RuntimeException("SCP failed: {$stderr}");
        }
    }

    public function executeCommand($command)
    {
        $sshCommand = "ssh {$this->remoteHost} '{$command}'";
        exec($sshCommand, $output, $returnVar);
        return [$output, $returnVar];
    }

    public function cleanupTempDir()
    {
        $cleanupCommand = "ssh {$this->remoteHost} 'rm -rf {$this->tempDir}'";
        exec($cleanupCommand, $output, $returnVar);
        if ($returnVar !== 0) {
            throw new \RuntimeException("Failed to clean up remote directory: {$this->tempDir}");
        }
    }
}
