<?php

use Illuminate\Support\Facades\Route;

use App\Models\ContestType;
use App\Models\User;

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\LocalizationController;
use App\Http\Controllers\User\ContestController;
use App\Http\Controllers\User\CountryController;
use App\Http\Controllers\User\ProblemController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\User\ContestRatingController;
use App\Http\Controllers\User\StandingController;
use App\Http\Controllers\User\SubmissionController;
use App\Http\Controllers\User\BlogController;

use App\Http\Controllers\Admin\FileController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ImageController as AdminImageController;
use App\Http\Controllers\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Admin\ContestController as AdminContestController;
use App\Http\Controllers\Admin\ProblemController as AdminProblemController;

use App\Http\Controllers\Internal\TestCaseDownloadController;

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


Route::prefix('contests')->group(function () {
    
    Route::get('/', [ContestController::class, 'index']);

    Route::prefix('{contest}')->group(function () {

        Route::middleware(['can:view,contest'])->group(function () {
            Route::get('/', [ContestController::class, 'show']);
            Route::get('/submit', [ContestController::class, 'submit']);
            Route::get('/standings', [StandingController::class, 'getByContest']);
            Route::get('/problem/{problem}/user/{user}', [ContestController::class, 'getContestProblemSubmissions']);
        });

        Route::middleware(['auth:sanctum', 'verified', 'can:view,contest'])->group(function () {
            Route::post('/register', [ContestController::class, 'register']);
            Route::post('/unregister', [ContestController::class, 'unregister']);
        });

    });
});


Route::prefix('problems')->group(function () {

    Route::get('/', [ProblemController::class, 'index']);

    Route::prefix('problem/{contest}/{char}')->middleware(['can:view,contest'])->group(function () {
        Route::get('/', [ProblemController::class, 'show']);
        Route::get('/attachments', [ProblemController::class, 'getAttachments']);
    });

    Route::get('/standings', [ProblemController::class, 'standings']);

});


Route::prefix('submissions')->group(function () {

    Route::get('/', [SubmissionController::class, 'index']);
    Route::get('/submission/{submission}', [SubmissionController::class, 'show'])->middleware(['can:view,submission']);
    Route::post('/problem/{code}/submit', [SubmissionController::class, 'store'])->middleware(['auth:sanctum', 'verified']);
    
});


Route::group(['prefix' => '/profile/{handle}'], function () {

    Route::get('/', [UserController::class, 'show']);
    Route::get('/submissions', [UserController::class, 'submissions']);
    Route::get('/ratings', [UserController::class, 'ratings']);
    Route::get('/edit', [UserController::class, 'edit'])->middleware(['auth:sanctum', 'verified']);
    Route::post('/update', [UserController::class, 'update'])->middleware(['auth:sanctum', 'verified']);

});


Route::get('/ratings', [ContestRatingController::class, 'index']);

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
            Route::get('/add/ratings', [ContestRatingController::class, 'store']);

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

Route::group(['prefix' => 'internal', 'middleware' => 'internal'], function () {
    Route::get('/test-cases/{problem}', [TestCaseDownloadController::class, 'download'])->name('internal.test-cases.download');
});