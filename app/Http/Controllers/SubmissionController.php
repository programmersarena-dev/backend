<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitProblemRequest;
use App\Models\Contest;
use App\Models\Problem;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Jobs\TestCodeJob;
use App\Http\Resources\SubmissionResource;

class SubmissionController extends Controller
{
    public function index()
    {
        return SubmissionResource::collection(Submission::query()->orderBy('id', 'desc')->paginate(100));
    }

    public function getByProblem($contestProblem, Request $request)
    {
        list($contest_id, $problem_char) = explode("-", $contestProblem);
        $contest = Contest::findOrFail($contest_id);
        $problem = $contest->getProblemByCharacter($problem_char);
        $user = Auth::guard('sanctum')->user();
        if ($request->my == 'true') {
            return SubmissionResource::collection(Submission::query()->where('problem_id', $problem->id)->where('user_id', $user->id)->orderBy('id', 'desc')->paginate(100));
        }
        return SubmissionResource::collection(Submission::query()->where('problem_id', $problem->id)->orderBy('id', 'desc')->paginate(100));
    }

    public function getById(Submission $submission, Request $request)
    {
        $contest = $submission->problem->contest;
        $problems = Problem::where('contest_id', $contest->id)->orderBy('id', 'asc')->get();
        $char = 'A';
        foreach ($problems as $index => $problem) {
            if ($problem->id == $submission->problem->id) {
                $char = chr(ord('A') + $index);
                break;
            }
        }

        $user = Auth::guard('sanctum')->user();

        if ($contest->getStatus() != 'ended') {
            if ($user && $submission->user_id == $user->id) {
                $code = json_decode($submission->code);
                $outputs = [];
            } else {
                $code = "";
                $outputs = [];
            }
        } else {
            $code = json_decode($submission->code);
            $outputs = json_decode($submission->outputs);
        }
        return [
            'id' => $submission->id,
            'username' => $submission->user->name,
            'contest' => [
                'id' => $contest->id,
                'name' => $contest->name,
            ],
            'problem_char' => $char,
            'language' => $submission->language,
            'verdict' => $submission->verdict,
            'time' => $submission->time() . ' ms',
            'memory' => $submission->memory() . ' KB',
            'sent_time' => $submission->created_at,
            'code' => $code,
            'outputs' => $outputs,
        ];
    }

    public function store(Contest $contest, $char, SubmitProblemRequest $request)
    {
        $validatedData = $request->validated();
        $problem = $contest->getProblemByCharacter($char);

        $submission = $problem->submissions()->where('user_id', Auth::user()->id)->latest();
        if ($submission->exists() && $submission->first()->created_at->diffInSeconds(Carbon::now()) < 30) {
            return response()->json(['message' => '30 sekünden az aralykda tabşyryş edip bolmaz'], 422);
        }

        $file = $request->file('file');
        $code = $request->input('code');
        $languageWithVersion = $request->input('language');
        list($language, $version) = explode("-", $languageWithVersion);
        $language = config('languages.dockerLanguages')[$language];
        $codeContent = $code ? $code : file_get_contents($file->getRealPath());

        if (env('SSH_DOCKER_IP') == 'localhost' || env('SSH_DOCKER_IP') == '127.0.0.1') {
            $host = new \App\Services\LocalHostService();
        } else {
            $host = new \App\Services\RemoteHostService();
        }

        if ($file)
            $host->saveFile($file, "submission." . $language['extension']);
        if ($code)
            $host->saveText($code, "submission." . $language['extension']);

        $host->moveFile(
            storage_path('app/public/' . $problem->test_cases . '/check'),
            "check"
        );

        $submission = Submission::create([
            'user_id' => Auth::user()->id,
            'problem_id' => $problem->id,
            'language' => $languageWithVersion,
            'code' => json_encode($codeContent),
            'verdict' => 'Compiling',
        ]);

        TestCodeJob::dispatch($host, $submission, $language, $version);
        return response()->json(['message' => 'Iberilen kod tabşyryldy we kompilýasiýa edilýär']);
    }
}
