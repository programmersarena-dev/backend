<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('dispatch:unverified-users')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected $commands = [
        // \App\Console\Commands\DispatchUnverifiedUsersJob::class,
        \App\Console\Commands\JudgeQueueWorker::class,
        \App\Console\Commands\ListenJudgeResults::class,
    ];
}
