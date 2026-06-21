<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessUnverifiedUsers;

class DispatchUnverifiedUsersJob extends Command
{
    protected $signature = 'dispatch:unverified-users';
    protected $description = 'Dispatch job to delete unverified users';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        ProcessUnverifiedUsers::dispatch();
        $this->info('Unverified users job dispatched!');
    }
}