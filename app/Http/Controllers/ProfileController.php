<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubmissionResource;
use App\Http\Resources\UserResource;
use App\Models\Contest;
use App\Models\Profile;
use App\Http\Requests\StoreProfileRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Standing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProfileRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($handle)
    {
        return new UserResource(
            User::query()
                ->where('handle', $handle)
                ->firstOrFail()
        );
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
        $profile['current_image'] = $user->profile->image ? asset('storage/' . $user->profile->image) : '';
        return response()->json($profile);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProfileRequest $request)
    {
        if (!$request->user()) {
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


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Profile $profile)
    {
        //
    }

    public function ratings($handle)
    {
        $user = User::where('handle', $handle)->firstOrFail();
        $contest_ratings = [];
        $new_rating = 0;
        if ($user->rating) {
            foreach (json_decode($user->rating->contest_ratings) as $index => $contest_rating) {
                $contest = Contest::findOrFail($contest_rating->contest_id);
                $standing = Standing::findOrFail($contest->id);
                $userContestResult = $standing->userContestResult($user->name);
                $rating = $contest_rating->rating;
                $new_rating += $rating;

                $contest_ratings[] = [
                    'id' => $index + 1,
                    'contest' => [
                        'id' => $contest->id,
                        'name' => $contest->name,
                    ],
                    'rank' => $userContestResult->place,
                    'solved' => count(array_filter($userContestResult->problems, fn($problem) => $problem->score > 0)),
                    'rating' => $rating,
                    'new_rating' => $new_rating,
                ];
            }

            usort($contest_ratings, function ($a, $b) {
                return $b['id'] <=> $a['id'];
            });
        }

        return [
            'current_rating' => $user->rating ? $user->rating->current_rating : 0,
            'contest_ratings' => $contest_ratings,
        ];
    }

    public function submissions($handle)
    {
        $submissions = User::query()->where('handle', $handle)->firstOrFail()->submissions();
        return SubmissionResource::collection($submissions->orderBy('id', 'desc')->paginate(100));
    }
}
