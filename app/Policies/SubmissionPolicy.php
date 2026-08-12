<?php

namespace App\Policies;

use App\Models\Problem;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SubmissionPolicy
{
    /**
     * Determine whether the user can store a new submission for a problem.
     */
    public function create(User $user, Problem $problem, ?string $language = null): Response
    {
        $contest = $problem->contest;

        if (!$contest) {
            return Response::deny('Bäsleşik tapylmady', 404);
        }

        if ($contest->status === 'Pending') {
            return Response::deny('Bäsleşik başlamady', 403);
        }

        if (!$contest->canUserSubmit($user->id)) {
            return Response::deny('Size şu wagt meseläniň çözüwini ugratmaga rugsat berilmeýär', 403);
        }

        if ($language && !in_array($language, $problem->acceptableLanguages())) {
            return Response::deny('Dil näsazlygy', 403);
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can view the submission source code and outputs.
     */
    public function view(?User $user, Submission $submission): Response
    {
        $contest = $submission->problem?->contest;
        $isContestEnded = $contest?->status === 'Ended';
        $isOwner = $user && $submission->user_id === $user->id;

        return ($isContestEnded || $isOwner)
            ? Response::allow()
            : Response::deny('Çözüwi görmäge rugsat berilmeýär', 403);
    }
}