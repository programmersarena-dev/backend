<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Judge Server Configurations
    |--------------------------------------------------------------------------
    |
    | You can define multiple judge servers used for competitive programming
    | problem testing. This can include endpoints, time limits, memory limits,
    | sandbox paths, etc.
    |
    */

    'servers' => [
        'server_1' => [
            'user' => env('JUDGE_SSH_USER', 'user'),
            'ip' => env('JUDGE_SSH_IP', '127.0.0.1'),
            'tmp' => env('JUDGE_TMP_DIR', '/tmp'),
        ],
    ],

];
