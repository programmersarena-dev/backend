<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        $ownedTeams = $user->ownedTeams()->with(['members'])->get()->map(fn($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'role' => 'owner',
            'members' => $team->members->map(fn($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'handle' => $member->handle,
                'status' => $member->pivot->status,
            ]),
        ]);

        $memberships = $user->teamMemberships()
            ->wherePivot('status', 'accepted')
            ->with(['owner'])
            ->get()
            ->map(fn($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'role' => 'member',
                'owner' => $team->owner ? [
                    'id' => $team->owner->id,
                    'name' => $team->owner->name,
                    'handle' => $team->owner->handle,
                ] : null,
                'members' => $team->members->map(fn($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'handle' => $member->handle,
                    'status' => $member->pivot->status,
                ]),
            ]);

        $pendingInvitations = $user->teamMemberships()
            ->wherePivot('status', 'pending')
            ->with(['owner'])
            ->get()
            ->map(fn($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'owner' => $team->owner ? [
                    'id' => $team->owner->id,
                    'name' => $team->owner->name,
                    'handle' => $team->owner->handle,
                ] : null,
            ]);

        return response()->json([
            'owned_teams' => $ownedTeams,
            'memberships' => $memberships,
            'pending_invitations' => $pendingInvitations,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        $request->validate([
            'name' => ['required', 'string', 'max:64'],
        ]);

        $team = Team::create([
            'owner_id' => $user->id,
            'name' => $request->input('name'),
        ]);

        $team->members()->attach($user->id, ['status' => 'accepted']);

        return response()->json([
            'message' => 'Team created successfully',
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'role' => 'owner',
                'members' => [[
                    'id' => $user->id,
                    'name' => $user->name,
                    'handle' => $user->handle,
                    'status' => 'accepted',
                ]],
            ],
        ], 201);
    }

    public function invite(Request $request, Team $team): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        if ($team->owner_id !== $user->id) {
            return response()->json(['message' => 'Only team owner can invite members'], 403);
        }

        $request->validate([
            'handle' => ['required', 'string', 'exists:users,handle'],
        ]);

        $targetUser = User::where('handle', $request->input('handle'))->first();

        if ($targetUser->id === $user->id) {
            return response()->json(['message' => 'Cannot invite yourself'], 422);
        }

        $existing = $team->members()->where('users.id', $targetUser->id)->exists();
        if ($existing) {
            return response()->json(['message' => 'User is already in this team'], 409);
        }

        $team->members()->attach($targetUser->id, ['status' => 'pending']);

        return response()->json(['message' => 'Invitation sent successfully']);
    }

    public function acceptInvitation(Team $team): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        $membership = $team->members()->where('users.id', $user->id)->first();
        if (!$membership || $membership->pivot->status !== 'pending') {
            return response()->json(['message' => 'No pending invitation found'], 404);
        }

        $team->members()->updateExistingPivot($user->id, [
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        return response()->json(['message' => 'Invitation accepted']);
    }

    public function declineInvitation(Team $team): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        $membership = $team->members()->where('users.id', $user->id)->first();
        if (!$membership || $membership->pivot->status !== 'pending') {
            return response()->json(['message' => 'No pending invitation found'], 404);
        }

        $team->members()->detach($user->id);

        return response()->json(['message' => 'Invitation declined']);
    }

    public function removeMember(Request $request, Team $team, User $member): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        if ($team->owner_id !== $user->id) {
            return response()->json(['message' => 'Only team owner can remove members'], 403);
        }

        if ($member->id === $team->owner_id) {
            return response()->json(['message' => 'Cannot remove team owner'], 422);
        }

        $team->members()->detach($member->id);

        return response()->json(['message' => 'Member removed successfully']);
    }

    public function destroy(Team $team): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        if ($team->owner_id !== $user->id) {
            return response()->json(['message' => 'Only team owner can delete team'], 403);
        }

        $team->delete();

        return response()->json(['message' => 'Team deleted successfully']);
    }
}
