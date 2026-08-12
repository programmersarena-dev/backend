<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

use App\Models\Contest;
use App\Models\Problem;
use App\Models\Submission;

use App\Jobs\GradeSubmissionJob;

use App\Http\Requests\StoreSubmissionRequest;

use App\Http\Resources\User\Submission\SubmissionListResource;
use App\Http\Resources\User\Submission\SubmissionDetailResource;

class SubmissionController extends Controller
{
    /**
     * Fetch paginated list of all submissions.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        if ($request->has('contest_id') && $request->has('char')) {
            $contest = Contest::find($request->contest_id);
            if (!$contest) {
                return response()->json(['message' => __('messages.contest_not_found')], 404);
            }
            $problem = $contest->getProblemByCharacter($request->char);
            if (!$problem) {
                return response()->json(['message' => __('messages.problem_not_found')], 404);
            }
            $submissions = Submission::where('problem_id', $problem->id)
                ->with(['user', 'problem'])
                ->orderBy('id', 'desc')
                ->paginate(100);

            return SubmissionListResource::collection($submissions);
        }

        return SubmissionListResource::collection(
            Submission::query()
                ->with(['user', 'problem'])
                ->orderBy('id', 'desc')
                ->paginate(100)
        );
    }

    /**
     * Fetch single submission layout with structural anti-cheat evaluation.
     */
    public function show(Submission $submission)
    {
        $submission->load(['user', 'problem.contest']);
        return new SubmissionDetailResource($submission);
    }

    /**
     * Store and process a user source code transaction.
     */
    public function store(StoreSubmissionRequest $request, string $code): JsonResponse
    {
        $problem = $request->problem();
        $userId = Auth::id();

        $lastSubmission = $problem->submissions()
            ->where('user_id', $userId)
            ->latest('id')
            ->first(['created_at']);

        if ($lastSubmission && $lastSubmission->created_at->diffInSeconds(now()) < 30) {
            return response()->json(['message' => __('messages.submission_rate_limit')], 422);
        }

        $codeContent = $request->input('code');
        if (!$codeContent && $request->hasFile('file')) {
            $codeContent = file_get_contents($request->file('file')->getRealPath());
        }

        $languageWithVersion = $request->input('language');
        [$languageKey, $version] = array_pad(explode('-', $languageWithVersion, 2), 2, '');

        $submission = Submission::create([
            'user_id'    => $userId,
            'problem_id' => $problem->id,
            'contest_id' => $problem->contest_id,
            'language'   => $languageWithVersion,
            'code'       => $codeContent,
            'status'     => 'Queued',
        ]);

        GradeSubmissionJob::dispatch(
            $submission->id,
            $languageKey,
            $version,
            $problem->time_limit ?? 1,
            $problem->memory_limit ?? 256
        );

        return response()->json(['message' => __('messages.submission_success')], 201);
    }
}
