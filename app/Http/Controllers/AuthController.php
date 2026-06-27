<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\SignUpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    // Token Lifetime Constants
    private const ACCESS_TOKEN_EXPIRY_MINUTES = 15;
    private const REFRESH_TOKEN_EXPIRY_MINUTES = 14400; // 10 Days
    private const REFRESH_COOKIE_NAME = 'refresh_token';

    /**
     * Register a new user and return structural access metrics.
     */
    public function signup(SignUpRequest $request)
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($data) {
            /** @var User $user */
            $user = User::create([
                'handle' => $data['handle'],
                'name' => $data['first_name'] + ' ' + $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            Profile::create([
                'user_id' => $user->id,
                'country_id' => $data['country_id'],
            ]);

            $user->sendEmailVerificationNotification();

            return $user;
        });

        // Issue tokens directly upon successful sign-up
        [$accessToken, $cookie] = $this->issueTokens($result);

        return response()->json([
            'message' => __('messages.signup_success'), // Dynamic Localization
            'user' => $result,
            'token' => $accessToken
        ], 201)->withCookie($cookie);
    }

    /**
     * Authenticate user, evaluate active device concurrency constraints,
     * and attach stateful HttpOnly refresh keys.
     */
    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        $loginField = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'handle';

        $user = User::where($loginField, $data['login'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => __('messages.invalid_credentials')], 422);
        }

        if ($user->last_activity) {
            $lastActivity = Carbon::parse($user->last_activity)->addMinutes(10);

            if ($lastActivity->isFuture()) {
                $user->tokens()->delete();
            }
        }

        $user->forceFill(['last_activity' => Carbon::now()])->save();

        [$accessToken, $cookie] = $this->issueTokens($user);

        return response()->json([
            'message' => __('messages.login_success'),
            'user' => $user,
            'token' => $accessToken
        ])->withCookie($cookie);
    }

    /**
     * Mark the authenticated user's email address as verified.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|int  $id
     * @param  string  $hash
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request, $id, $hash): JsonResponse
    {
        if (!$request->hasValidSignature()) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.email_invalid_signature', [], 'tk')
            ], 403);
        }

        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.email_invalid_hash', [], 'tk')
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'success',
                'message' => __('messages.email_already_verified', [], 'tk')
            ], 200);
        }

        if ($user->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        return response()->json([
            'status' => 'success',
            'message' => __('messages.email_verified_success', [], 'tk')
        ], 200);
    }

    /**
     * Parse incoming safe cookie payloads to issue regenerated token rotations.
     */
    public function refresh(Request $request)
    {
        $refreshToken = $request->cookie(self::REFRESH_COOKIE_NAME);

        if (!$refreshToken) {
            return response()->json(['message' => __('messages.unauthorized')], 401);
        }

        // Parse and validate the token from Sanctum's personal access token store
        $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($refreshToken);

        if (!$tokenModel || $tokenModel->expires_at?->isPast()) {
            return response()->json(['message' => __('messages.unauthorized')], 401);
        }

        /** @var User $user */
        $user = $tokenModel->tokenable;

        // Clean up both the old single-use access and old refresh strings to prevent creep
        $tokenModel->delete();

        [$newAccessToken, $newCookie] = $this->issueTokens($user);

        return response()->json([
            'token' => $newAccessToken
        ])->withCookie($newCookie);
    }

    /**
     * Terminate the API Bearer sessions and explicitly clear stateful HttpOnly cookies.
     */
    public function logout(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user) {
            $currentAccessToken = $user->currentAccessToken();

            if ($currentAccessToken && property_exists($currentAccessToken, 'id')) {
                $user->tokens()->where('id', $currentAccessToken->id)->delete();
            }

            // Remove long-lived refresh references explicitly from the database
            if ($refreshToken = $request->cookie(self::REFRESH_COOKIE_NAME)) {
                if ($tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($refreshToken)) {
                    $tokenModel->delete();
                }
            }

            $user->setRememberToken(null);
            $user->save();

            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        // Clear the cookie inside the client browser by forcing instant expiration
        $forgetCookie = cookie()->forget(self::REFRESH_COOKIE_NAME);

        return response()->json([
            'success' => true,
            'message' => __('messages.logout_success')
        ])->withCookie($forgetCookie);
    }

    public function me()
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return response()->json([
                'message' => __('messages.unauthorized')
            ], 401);
        }

        return response()->json($user);
    }

    public function user_activity()
    {
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            $user->forceFill(['last_activity' => Carbon::now()])->saveQuietly();
        }

        return response()->noContent();
    }

    /**
     * Architecture Helper: Atomic generation of standard short access arrays
     * paired with long security cookie properties.
     */
    private function issueTokens(User $user): array
    {
        // 1. Create Short-Lived Access Token string
        $accessExpiry = Carbon::now()->addMinutes(self::ACCESS_TOKEN_EXPIRY_MINUTES);
        $accessTokenResult = $user->createToken('access_token', ['*'], $accessExpiry);

        // 2. Create Long-Lived Refresh Token string (FIXED PROPERTY NAME HERE)
        $refreshExpiry = Carbon::now()->addMinutes(self::REFRESH_TOKEN_EXPIRY_MINUTES);
        $refreshTokenResult = $user->createToken('refresh_token', ['issue-access-token'], $refreshExpiry);

        // 3. Encapsulate Refresh string into a secure back-channel cookie instance
        $cookie = cookie(
            self::REFRESH_COOKIE_NAME,
            $refreshTokenResult->plainTextToken,
            self::REFRESH_TOKEN_EXPIRY_MINUTES,
            '/',                  // Path
            null,                 // Domain
            true,                 // Secure (HTTPS)
            true,                 // HttpOnly
            false,                // Raw
            Cookie::SAMESITE_STRICT // SameSite Cross-Origin Mitigation
        );

        return [$accessTokenResult->plainTextToken, $cookie];
    }
}
