<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Judge Server Configurations
    |--------------------------------------------------------------------------
    |
    | Define judge execution mode and queue settings for the judge-box service
    |
    */

    'mode' => env('JUDGE_MODE', 'queue'), // 'direct' (old) or 'queue' (new judge-box)

    'queue' => [
        'driver' => 'redis',
        'connection' => 'default',
        'job_queue_key' => 'judge:jobs',
        'result_queue_key' => 'judge:results',
    ],

    'direct' => [
        'servers' => [
            'server_1' => [
                'user' => env('JUDGE_SSH_USER', 'user'),
                'ip' => env('JUDGE_SSH_IP', '127.0.0.1'),
                'tmp' => env('JUDGE_TMP_DIR', '/tmp'),
            ],
        ],
    ],

];
