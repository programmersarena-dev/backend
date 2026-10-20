<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\User\Submission\SubmissionListResource;
use App\Http\Resources\User\UserResource;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show($handle)
    {
        $user = User::query()->with('profile.country')
            ->withCount(['submissions as accepted_problems_count' => fn ($query) => $query
                ->accepted()->select(DB::raw('count(distinct problem_id)'))])
            ->where('handle', $handle)->first();
        if (! $user) {
            return response('', 404);
        }

        return new UserResource($user);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($handle, Request $request)
    {
        if ($handle != $request->user()->handle) {
            return response('', 404);
        }
        $user = $request->user();
        $profile = $user->profile->only(['first_name', 'last_name', 'country_id']);
        $profile['current_image'] = $user->profile->image ? asset('storage/'.$user->profile->image) : '';

        return response()->json($profile);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProfileRequest $request)
    {
        if (! $request->user()) {
            return response('', 404);
        }

        $user = $request->user();
        $profile = $user->profile;

        $profile->first_name = $request->input('first_name');
        $profile->last_name = $request->input('last_name');
        $profile->country_id = $request->input('country_id');

        if ($request->filled('password')) {
            if (Hash::check($request->input('old_password'), $user->password)) {
                $user->password = Hash::make($request->input('password'));
            } else {
                return response()->json(['message' => 'Köne parol nädogry'], 400);
            }
        }

        if ($request->hasFile('image')) {
            if ($profile->image) {
                Storage::disk('public')->delete($profile->image);
            }

            $path = $request->file('image')->store('profile_images', 'public');
            $profile->image = $path;
        }

        $user->save();
        $profile->save();

        return response()->json(['message' => 'Profil üstünlikli täzelendi', 'user' => $user]);
    }

    public function ratings($handle)
    {
        $user = User::where('handle', $handle)->firstOrFail();
        $contestRatings = $user->contestRatings()->with('contest:id,name')
            ->orderByDesc('id')->get()->map(fn ($rating) => [
                'id' => $rating->id,
                'contest' => ['id' => $rating->contest_id, 'name' => $rating->contest?->name],
                'rank' => $rating->rank,
                'solved' => $rating->solved,
                'rating' => $rating->rating_change,
                'new_rating' => $rating->new_rating,
            ]);

        return [
            'current_rating' => (int) $user->current_rating,
            'contest_ratings' => $contestRatings,
        ];
    }

    public function submissions(Request $request, $handle)
    {
        $submissions = User::query()->where('handle', $handle)->firstOrFail()->submissions();

        return SubmissionListResource::collection($submissions
            ->visibleTo(Auth::guard('sanctum')->user())
            ->with(['user:id,handle', 'problem.translations', 'problem.contest.problems:id,contest_id'])
            ->orderByDesc('id')->paginate(Submission::perPage($request))->withQueryString());
    }
}
