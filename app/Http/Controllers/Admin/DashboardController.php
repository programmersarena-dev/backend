<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Contest;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $usersCount = User::query()->count();
        $contestsCount = Contest::query()->count();
        $problemsCount = Problem::query()->count();
        $submissionsCount = Submission::query()->count();

        $now = Carbon::now();
        $startDate = $now->copy()->subMonths(11)->startOfMonth();

        $users = User::selectRaw("
            YEAR(created_at) as year,
            MONTH(created_at) as month,
            COUNT(*) as total
            ")
            ->where('created_at', '>=', $startDate->format('Y-m-d'))
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $contests = Contest::selectRaw("
            YEAR(start_date) as year,
            MONTH(start_date) as month,
            COUNT(*) as total
            ")
            ->where('start_date', '>=', $startDate->format('Y-m-d'))
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $problems = Problem::selectRaw("
            YEAR(created_at) as year,
            MONTH(created_at) as month,
            COUNT(*) as total
            ")
            ->where('created_at', '>=', $startDate->format('Y-m-d'))
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $submissions = Submission::selectRaw("
            YEAR(created_at) as year,
            MONTH(created_at) as month,
            COUNT(*) as total
            ")
            ->where('created_at', '>=', $startDate->format('Y-m-d'))
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return [
            'usersCount' => $usersCount,
            'contestsCount' => $contestsCount,
            'problemsCount' => $problemsCount,
            'submissionsCount' => $submissionsCount,
            'users' => $users,
            'contests' => $contests,
            'problems' => $problems,
            'submissions' => $submissions,
        ];
    }
}
