<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\SignUpRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Added for Transactions
use Illuminate\Support\Facades\Hash; // Added for password checking

class AuthController extends Controller
{
    public function signup(SignUpRequest $request)
    {
        $data = $request->validated();

        // Use a Transaction to ensure both User and Profile are created, or neither.
        $result = DB::transaction(function () use ($data) {

            /** @var \App\Models\User $user */
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']), // Hash::make is preferred over bcrypt() helper
            ]);

            Profile::create([
                'user_id' => $user->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'country_id' => $data['country_id'],
            ]);

            $token = $user->createToken('main')->plainTextToken;

            // Optional: Move email sending outside transaction or use Queues to prevent lag
            $user->sendEmailVerificationNotification();

            return [
                'user' => $user,
                'token' => $token
            ];
        });

        return response([
            'message' => 'Hasabyňyz üstünlikli döredildi. E-poçtaňyzy barlaň!',
            'user' => $result['user'],
            'token' => $result['token']
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        // Check password manually to avoid starting a Session (Auth::attempt) if this is pure API
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Berlen maglumatlar dogry däl'], 422);
        }

        // --- SINGLE DEVICE LOGIC (IMPROVED) ---
        // Instead of blocking the user for 10 minutes, we invalidate the PREVIOUS sessions.
        // This prevents the "I closed my tab and now I'm locked out" error.

        if ($user->last_activity) {
            $lastActivity = Carbon::parse($user->last_activity)->addMinutes(10);
            $now = Carbon::now('UTC');

            // If user is active elsewhere, we have two choices:
            // 1. Block them (Your original code)
            // 2. Kick the other device (Recommended code below)

            if ($lastActivity > $now) {
                 // Option A: Strictly Single Device (Kicks out previous user)
                 $user->tokens()->delete();

                 // Option B: If you really want to block them, keep your original if statement here.
            }
        }

        // Update activity immediately on login
        $user->last_activity = Carbon::now();
        $user->save();

        $token = $user->createToken('main')->plainTextToken;

        return response([
            'message' => 'Giriş üstünlikli',
            'user' => $user,
            'token' => $token
        ]);
    }

    public function logout(Request $request)
    {
        /** @var User $user */
        // Use Auth::user() which returns the authenticated user regardless of the guard (sanctum or web)
        $user = Auth::user();

        if ($user) {
            $currentAccessToken = $user->currentAccessToken();

            if ($currentAccessToken && property_exists($currentAccessToken, 'id')) {
                // Delete the API token record using its ID via the relationship
                $user->tokens()->where('id', $currentAccessToken->id)->delete();
            }

            // Clear Remember Token (for web guard/session persistence)
            $user->setRememberToken(null);
            $user->save();

            // Check if the user is authenticated via the session (web guard)
            // We use the default web guard to explicitly perform session logout, preventing the error.
            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return response([
            'success' => true,
            'message' => 'Ulgamdan çykyldy'
        ]);
    }

    public function me()
    {
        return Auth::guard('sanctum')->user();
    }

    // Best Practice: Move this logic to a Middleware later
    public function user_activity()
    {
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            // Use updateQuietly if you have Observers you don't want to trigger
            $user->forceFill(['last_activity' => Carbon::now()])->save();
        }

        return response()->noContent();
    }
}
