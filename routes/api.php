<?php

use App\Http\Controllers\Admin\ImageController as AdminImageController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ContestController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\LocalizationController;
use App\Http\Controllers\ProblemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\StandingController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Admin\ContestController as AdminContestController;
use App\Http\Controllers\Admin\ProblemController as AdminProblemController;
use App\Models\ContestType;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('auth')->group(function () {
    Route::post('/signup', [AuthController::class, 'signup']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/activity', [AuthController::class, 'user_activity']);
});

Route::group(['prefix' => '/email'], function () {
    Route::get('/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed'])->name('verification.verify');
    Route::post('/resend', [EmailVerificationController::class, 'resend'])->middleware(['auth:sanctum', 'throttle:6,1'])->name('verification.resend');
});

Route::post('/locale', [LocalizationController::class, 'setLocale'])->name('api.locale.set');

Route::get('/countries', [CountryController::class, 'index'])->name('api.countries.index');

Route::group(['prefix' => '/blogs'], function () {
    Route::get('/', [BlogController::class, 'index']);
});

Route::get('/contests', [ContestController::class, 'index']);
Route::group(['prefix' => '/contest/{contest}'], function () {
    Route::post('/register', [ContestController::class, 'register'])->middleware(['auth:sanctum', 'verified']);
    Route::post('/unregister', [ContestController::class, 'unregister'])->middleware(['auth:sanctum', 'verified']);
    Route::get('/problems', [ContestController::class, 'problems']);
    Route::get('/problem/{problemId}/user/{user}', [ContestController::class, 'getContestProblemSubmissions']);
    Route::get('/standings', [StandingController::class, 'getByContest']);
});

Route::group(['prefix' => '/problemset'], function () {
    Route::get('/', [ProblemController::class, 'index']);
    Route::get('/problem/{contest}/{char}', [ProblemController::class, 'getByChar']);
    Route::get('/problem/{contest}/{char}/attachments', [ProblemController::class, 'getAttachments']);
    Route::get('/standings', [StandingController::class, 'usersProblemStandings']);
    Route::post('/problem/{contest}/{char}/submit', [SubmissionController::class, 'store'])->middleware(['auth:sanctum', 'verified']);
});

Route::group(['prefix' => '/submissions'], function () {
    Route::get('/', [SubmissionController::class, 'index']);
    Route::get('/problem/{contestProblem}', [SubmissionController::class, 'getByProblem']);
    Route::get('/submission/{submission}', [SubmissionController::class, 'getById']);
});

Route::group(['prefix' => '/profile/{username}'], function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::get('/submissions', [ProfileController::class, 'submissions']);
    Route::get('/ratings', [ProfileController::class, 'ratings']);
    Route::get('/edit', [ProfileController::class, 'edit'])->middleware(['auth:sanctum', 'verified']);
    Route::post('/update', [ProfileController::class, 'update'])->middleware(['auth:sanctum', 'verified']);
});

Route::get('/ratings', [RatingController::class, 'index']);

// Admin
Route::group(['prefix' => '/admin', 'middleware' => 'admin'], function () {
    Route::get('/', [AdminDashboardController::class, 'index']);
    Route::prefix('/files')->group(function () {
        Route::get('/', [FileController::class, 'index']);
        Route::post('/upload', [FileController::class, 'store']);
        Route::post('/create-directory', [FileController::class, 'makeDirectory']);
        Route::delete('/delete', [FileController::class, 'destroy']);
    });
    Route::prefix('/images')->group(function () {
        Route::get('/', [AdminImageController::class, 'index']);
        Route::post('/upload', [AdminImageController::class, 'store']);
        Route::delete('/delete', [AdminImageController::class, 'destroy']);
    });
    Route::get('/users', function () {
        $users = User::orderBy('name', 'asc')->get();
        return response()->json($users);
    });
    Route::get('/contest-types', function () {
        $types = ContestType::orderBy('name', 'asc')->get();
        return response()->json($types);
    });
    Route::group(['prefix' => '/blog'], function () {
        Route::get('/', [AdminBlogController::class, 'index']);
        Route::post('/add', [AdminBlogController::class, 'store']);
        Route::get('/{blog}', [AdminBlogController::class, 'edit']);
        Route::post('/{blog}', [AdminBlogController::class, 'update']);
        Route::delete('/{blog}', [AdminBlogController::class, 'destroy']);
    });
    Route::get('/contests', [AdminContestController::class, 'index']);
    Route::group(['prefix' => '/contest'], function () {
        Route::post('/add', [AdminContestController::class, 'store']);
        Route::group(['prefix' => '/{contest}'], function () {
            Route::get('/', [AdminContestController::class, 'edit']);
            Route::post('/', [AdminContestController::class, 'update']);
            Route::delete('/delete', [AdminContestController::class, 'destroy']);
            Route::post('/notify', [AdminContestController::class, 'notifyUsers']);
            Route::get('/add/ratings', [RatingController::class, 'store']);

            Route::get('/problems', [AdminProblemController::class, 'index']);
            Route::group(['prefix' => '/problem'], function () {
                Route::post('/add', [AdminProblemController::class, 'store']);
                Route::get('/{char}', [AdminProblemController::class, 'edit']);
                Route::post('/{char}', [AdminProblemController::class, 'update']);
                Route::delete('/{char}/delete', [AdminProblemController::class, 'destroy']);
                Route::get('/{char}/download-test-cases', [AdminProblemController::class, 'downloadTestCases']);
            });
        });
    });
});
