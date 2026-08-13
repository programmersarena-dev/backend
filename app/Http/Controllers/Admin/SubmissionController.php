<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Contest;
use App\Models\Submission;
use App\Models\Problem;

use App\Jobs\GradeSubmissionJob;

class SubmissionController extends Controller
{
    public function recheckAllSubmssionsInContest(Contest $contest)
    {
        $submissions = Submission::where('contest_id', $contest->id)->get();
        foreach ($submissions as $submission) {
            $this->recheck($submission);
        }
        return response()->json([
            "message" => "All submissions have been re-graded",
        ]);
    }

    public function recheckAllSubmssionsInProblem(Contest $contest, $char)
    {
        $problem = $contest->getProblemByCharacter($char);
        if (!$problem) {
            return response()->json([
                "message" => "Problem not found",
            ], 404);
        }
        $submissions = Submission::where('problem_id', $problem->id)->get();
        foreach ($submissions as $submission) {
            $this->recheck($submission);
        }
        return response()->json([
            "message" => "All submissions have been re-graded",
        ]);
    }

    private function recheck(Submission $submission) {
        $submission->status = 'Queued';
        $submission->outputs = null;
        $submission->time = null;
        $submission->memory = null;
        $submission->error_message = null;
        $submission->judged_at = null;
        $submission->save();

        [$language, $version] = explode('-', $submission->language);
        GradeSubmissionJob::dispatch(
            $submission->id,
            $language,
            $version,
            $submission->problem->time_limit,
            $submission->problem->memory_limit
        );
    }
}
