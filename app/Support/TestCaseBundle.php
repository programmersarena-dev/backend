<?php

namespace App\Support;

use App\Models\Problem;

final class TestCaseBundle
{
    public static function version(Problem $problem): string
    {
        // Uploads replace the bundle path; edits also invalidate the cache.
        return hash('sha256', $problem->test_cases_path.'|'.$problem->updated_at);
    }

    public static function test(string $directory, string $name): array
    {
        if (! preg_match('/\A[a-zA-Z0-9_-][a-zA-Z0-9_.-]*\z/D', $name)) {
            throw new \RuntimeException('Invalid test case name.');
        }

        return [
            'input_file' => $directory.$name.'.in',
            'expected_output_file' => $directory.$name.'.out',
        ];
    }
}
