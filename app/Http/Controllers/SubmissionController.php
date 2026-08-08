<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitProblemRequest;
use App\Models\Contest;
use App\Models\Submission;
use App\Jobs\GradeSubmissionJob;
use App\Http\Resources\SubmissionResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class SubmissionController extends Controller
{
    /**
     * Fetch paginated list of all submissions.
     */
    public function index(): AnonymousResourceCollection
    {
        return SubmissionResource::collection(
            Submission::query()
                ->with(['user', 'problem'])
                ->orderBy('id', 'desc')
                ->paginate(100)
        );
    }

    /**
     * Fetch submissions filtered by problem character code.
     */
    public function getByProblem(string $contestProblem, Request $request): AnonymousResourceCollection
    {
        $parts = explode('-', $contestProblem, 2);
        if (count($parts) < 2) {
            abort(400, 'Invalid contest-problem syntax specification.');
        }

        [$contestId, $problemChar] = $parts;

        $contest = Contest::findOrFail($contestId);
        $problem = $contest->getProblemByCharacter($problemChar);

        $query = Submission::query()
            ->with(['user', 'problem'])
            ->where('problem_id', $problem->id)
            ->orderBy('id', 'desc');

        if ($request->query('my') === 'true') {
            $user = Auth::guard('sanctum')->user();
            if ($user) {
                $query->where('user_id', $user->id);
            }
        }

        return SubmissionResource::collection($query->paginate(100));
    }

    /**
     * Fetch single submission layout with structural anti-cheat evaluation.
     */
    public function getById(Submission $submission): JsonResponse
    {
        $submission->load(['user', 'problem.contest']);
        $contest = $submission->problem->contest;

        $user = Auth::guard('sanctum')->user();

        // Anti-cheat verification checks if contest has wrapped up or user is viewing their own work
        $isContestEnded = $contest->getStatus() === 'ended';
        $isOwner = $user && $submission->user_id === $user->id;
        $canViewDetails = $isContestEnded || $isOwner;

        return response()->json([
            'id' => $submission->id,
            'username' => $submission->user->name,
            'contest' => [
                'id' => $contest->id,
                'name' => $contest->name,
            ],
            'problem_char' => $submission->problem->char() ?? 'A',
            'language' => $submission->language,
            'status' => $submission->status,
            'time' => ($submission->time ?? 0) . ' ms',
            'memory' => ($submission->memory ?? 0) . ' KB',
            'sent_time' => $submission->created_at?->toIso8601String(),
            'code' => json_decode($submission->code) ?? $submission->code,
            'outputs' => $canViewDetails ? json_decode($submission->outputs) : [],
        ]);
    }

    /**
     * Store and process a user source code transaction.
     */
    public function store(Contest $contest, string $char, SubmitProblemRequest $request): JsonResponse
    {
        $request->validated();
        $problem = $contest->getProblemByCharacter($char);
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
            'user_id' => $userId,
            'problem_id' => $problem->id,
            'language' => $languageWithVersion,
            'code' => json_encode($codeContent),
            'status' => 'Queued',
        ]);

        GradeSubmissionJob::dispatch(
            $submission->id,
            $languageKey,
            $version,
            $problem->time_limit ?? 1,
            $problem->memory_limit ?? 256
        );

        return response()->json(['message' => __('messages.submission_success')]);
    }
}
