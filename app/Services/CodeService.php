<?php

namespace App\Services;

class CodeService
{
    private $host;
    private $languages;
    private $dockerImage;
    private $language;
    private $version;
    private $compilationTimeLimit;
    private $compilationMemoryLimit;

    public function __construct($host, $language = null, $version = null, $compilationTimeLimit = null, $compilationMemoryLimit = null)
    {
        $this->host = $host;
        $this->languages = config('languages.dockerLanguages');
        $this->dockerImage = $language['dockerImage'] . ':' . $version;
        $this->language = $language;
        $this->version = $version;
        $this->compilationTimeLimit = $compilationTimeLimit;
        $this->compilationMemoryLimit = $compilationMemoryLimit;
    }

    public function compile($subtask = false)
    {
        if ($subtask) {
            $dockerCompileCommand = "docker run --rm --memory={$this->compilationMemoryLimit}m -v {$this->host->getTempDir()}:/submission {$this->dockerImage} sh -c \"{$this->language['commandCompileGrader']} 2>&1\"";
        } else {
            $dockerCompileCommand = "docker run --rm --memory={$this->compilationMemoryLimit}m -v {$this->host->getTempDir()}:/submission {$this->dockerImage} sh -c \"{$this->language['commandCompile']} 2>&1\"";
        }
        [$output, $returnVar] = $this->host->executeCommand($dockerCompileCommand);
        return $returnVar;
    }

    public function execute($input, $expectedOutput)
    {
        $dockerExecuteCommand = "docker run --rm --memory={$this->compilationMemoryLimit}m -v {$this->host->getTempDir()}:/submission {$this->dockerImage} sh -c \"/usr/bin/time -f \\\"\\nTime: %e\\nMemory: %M\\\" timeout {$this->compilationTimeLimit}s {$this->language['execute']} < /submission/input.txt 2>&1\"";
        [$output, $exitCode] = $this->host->executeCommand($dockerExecuteCommand);
        $outputStr = implode("\n", $output);
        preg_match("/\nTime: ([0-9.]+)\nMemory: ([0-9]+)(?: KB)?/", $outputStr, $matches);
        if ($this->language['dockerImage'] === 'php') {
            $outputLines = explode("\n", $outputStr);
            if (isset($outputLines[0]) && trim($outputLines[0]) === trim($input)) {
                array_shift($outputLines);
                $outputStr = implode("\n", $outputLines);
            }
        }
        return $this->getOutput($input, $expectedOutput, $exitCode, $outputStr, $matches);
    }

    private function getOutput($input, $expectedOutput, $exitCode, $outputStr, $matches)
    {
        $outputLines = explode("\n", $outputStr);
        $outputLines = array_filter($outputLines, function ($line) {
            return !preg_match("/^Time:|Memory:/", $line);
        });
        $outputStr = implode("\n", $outputLines);
        $outputStr = str_replace("\r\n", "\n", $outputStr);
        $outputStr = str_replace(" \n", "\n", $outputStr);
        $outputStr = trim($outputStr, " \n\r");

        $expectedOutputStr = $expectedOutput;
        $expectedOutputStr = str_replace("\r\n", "\n", $expectedOutputStr);
        $expectedOutputStr = str_replace(" \n", "\n", $expectedOutputStr);
        $expectedOutputStr = trim($expectedOutputStr, " \n\r");

        $executionTime = isset($matches[1]) ? intval(floatval($matches[1]) * 1000) : 'N/A';
        $memoryUsage = isset($matches[2]) ? intval($matches[2]) : 'N/A';

        $log = '';
        if ($exitCode == 124) {
            $log = 'TL';
        } elseif ($exitCode === 1 || ($exitCode > 0 && $exitCode !== 124)) {
            $log = 'RE';
        } elseif (strpos($outputStr, 'MemoryError') !== false || strpos($outputStr, 'std::bad_alloc') !== false || $exitCode == 137) {
            $log = 'ML';
        } elseif (strpos($outputStr, 'Floating point exception') !== false || $exitCode == 136) {
            $log = 'RE';
        } elseif (strpos($outputStr, 'Segmentation fault') !== false || $exitCode == 139) {
            $log = 'RE';
        } elseif (strpos($outputStr, 'Internal Error') !== false) {
            $log = 'IE';
        } elseif (strpos($outputStr, 'error') !== false || strpos($outputStr, 'exception') !== false) {
            $log = 'ER';
        } elseif (!$this->check($input, $outputStr, $expectedOutputStr)) {
            $log = 'WA';
        } else {
            $log = 'OK';
        }

        $input = $this->truncateString($input);
        $outputStr = $this->truncateString($outputStr);
        $expectedOutputStr = $this->truncateString($expectedOutputStr);

        return [
            'input' => $input,
            'output' => $outputStr,
            'expected_output' => $expectedOutputStr,
            'log' => $log,
            'time' => $executionTime,
            'memory' => $memoryUsage,
        ];
    }

    private function check($input, $output, $expectedOutput)
    {
        $this->host->saveText($input . "\n\n" . $output . "\n\n" . $expectedOutput, "check.txt");
        $dockerExecuteCommand = sprintf(
            'docker run --rm --network none --memory=256m --memory-swap=256m --user nobody -v %s:/submission gcc:10 sh -c "timeout %ds /submission/check < /submission/input.txt 2>&1"',
            $this->host->getTempDir(),
            1
        );

        [$outputStr, $exitCode] = $this->host->executeCommand($dockerExecuteCommand);
        $outputStr = implode("\n", $outputStr);
        if ($exitCode === 0 && $outputStr === "OK")
            return true;
        else
            return false;
    }

    private function truncateString($str, $maxLength = 500)
    {
        return strlen($str) > $maxLength ? substr($str, 0, $maxLength) . "\n..." : $str;
    }
}
