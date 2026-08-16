<?php

return [
    'dockerLanguages' => [
        'gcc' => [
            'dockerImage' => 'gcc',
            'versions' => ['10'],
            'extension' => 'cpp',
        ],
        'python' => [
            'dockerImage' => 'python',
            'versions' => ['3.10'],
            'extension' => 'py',
        ],
        'php' => [
            'dockerImage' => 'php',
            'versions' => ['8.0'],
            'extension' => 'php',
        ],
        'freepascal' => [
            'dockerImage' => 'freepascal',
            'versions' => ['3.2.2'],
            'extension' => 'pas',
        ],
        'golang' => [
            'dockerImage' => 'golang',
            'versions' => ['1.21.6'],
            'extension' => 'go',
        ],
        // 'abc' => [
        //     'dockerImage' => 'abc-pascal',
        //     'execute' => 'abc /submission/grader.abc',
        //     'versions' => ['1.0', '1.1'],
        //     'extension' => 'abc',
        // ],
        // 'java' => [
        //     'dockerImage' => 'openjdk',
        //     'commandCompile' => 'javac /submission/submission.java -d /submission/classes',
        //     'commandCompileGrader' => 'javac /submission/Grader.java /submission/submission.java -d /submission/classes',
        //     'execute' => 'java -cp /submission/classes Grader',
        //     'versions' => ['8', '11', '17'],
        //     'extension' => 'java',
        // ],
        // 'javascript' => [
        //     'dockerImage' => 'node',
        //     'execute' => 'node /submission/grader.js',
        //     'versions' => ['12', '14', '16'],
        //     'extension' => 'js',
        // ],
        // 'rust' => [
        //     'dockerImage' => 'rust',
        //     'commandCompile' => 'rustc /submission/submission.rs -o /submission/executable',
        //     'commandCompileGrader' => 'rustc -o /submission/executable /submission/grader.rs /submission/submission.rs',
        //     'execute' => '/submission/executable',
        //     'versions' => ['1.70', '1.75', 'stable'],
        //     'extension' => 'rs',
        // ],
        // 'go' => [
        //     'dockerImage' => 'golang',
        //     'commandCompile' => 'go build -o /submission/executable /submission/submission.go',
        //     'commandCompileGrader' => 'go build -o /submission/executable /submission/grader.go /submission/submission.go',
        //     'execute' => '/submission/executable',
        //     'versions' => ['1.20', '1.21', '1.22'],
        //     'extension' => 'go',
        // ],
        // 'ruby' => [
        //     'dockerImage' => 'ruby',
        //     'execute' => 'ruby /submission/grader.rb',
        //     'versions' => ['3.0', '3.1', '3.2'],
        //     'extension' => 'rb',
        // ],
        // 'csharp' => [
        //     'dockerImage' => 'mcr.microsoft.com/dotnet/sdk',
        //     'commandCompile' => 'dotnet build /submission --output /submission/binaries',
        //     'commandCompileGrader' => 'dotnet build /submission --output /submission/binaries',
        //     'execute' => 'dotnet run --project /submission/grader.csproj',
        //     'versions' => ['6.0', '7.0', '8.0'],
        //     'extension' => 'cs',
        // ],
        // 'lua' => [
        //     'dockerImage' => 'lua',
        //     'execute' => 'lua /submission/grader.lua',
        //     'versions' => ['5.3', '5.4'],
        //     'extension' => 'lua',
        // ],
    ],
];
