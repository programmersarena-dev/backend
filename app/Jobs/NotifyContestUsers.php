<?php

namespace App\Jobs;

use App\Models\Contest;
use App\Models\User;
use App\Notifications\ContestNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class NotifyContestUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $contestId) {}

    public function handle(): void
    {
        $contest = Contest::find($this->contestId);
        if (! $contest || $contest->status !== 'Pending') {
            return;
        }

        User::where('user_type', 'user')->whereNotNull('email_verified_at')
            ->select('id', 'name', 'email')->chunkById(200, function ($users) use ($contest) {
                Notification::send($users, (new ContestNotification($contest))
                    ->onConnection(config('queue.notifications_connection')));
            });
    }
}
