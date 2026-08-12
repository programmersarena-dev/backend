<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\Contest;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\User;

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
            TO_CHAR(created_at, 'YYYY-MM') as year_month,
            COUNT(*) as total
        ")
            ->where('created_at', '>=', $startDate->startOfMonth())
            ->groupBy('year_month')
            ->orderBy('year_month', 'asc')
            ->get();

        $contests = Contest::selectRaw("
                TO_CHAR(created_at, 'YYYY-MM') as year_month,
                COUNT(*) as total
        ")
            ->where('start_date', '>=', $startDate->startOfMonth())
            ->groupBy('year_month')
            ->orderBy('year_month', 'asc')
            ->get();

        $problems = Problem::selectRaw("
                TO_CHAR(created_at, 'YYYY-MM') as year_month,
                COUNT(*) as total
            ")
            ->where('created_at', '>=', $startDate->startOfMonth())
            ->groupBy('year_month')
            ->orderBy('year_month', 'asc')
            ->get();

        $submissions = Submission::selectRaw("
                TO_CHAR(created_at, 'YYYY-MM') as year_month,
                COUNT(*) as total
            ")
            ->where('created_at', '>=', $startDate->startOfMonth())
            ->groupBy('year_month')
            ->orderBy('year_month', 'asc')
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
