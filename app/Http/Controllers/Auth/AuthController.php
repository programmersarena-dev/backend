<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    private const ACCESS_TOKEN_EXPIRY_MINUTES = 15;
    private const REFRESH_TOKEN_EXPIRY_MINUTES = 14400; // 10 Days
    private const REFRESH_COOKIE_NAME = 'refresh_token';

    public function signup(SignUpRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            /** @var User $user */
            $user = User::create([
                'handle' => $data['handle'],
                'name' => trim($data['first_name'] . ' ' . $data['last_name']),
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            Profile::create([
                'user_id' => $user->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'country_id' => $data['country_id'],
            ]);

            $user->sendEmailVerificationNotification();

            return $user;
        });

        [$accessToken, $cookie] = $this->issueTokens($user);

        return response()->json([
            'message' => __('messages.signup_success'),
            'user' => $user,
            'token' => $accessToken,
        ], 201)->withCookie($cookie);
    }

    public function login(LoginRequest $request): JsonResponse
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
            'token' => $accessToken,
        ])->withCookie($cookie);
    }

    public function verify(Request $request, $id, $hash): JsonResponse
    {
        if (!$request->hasValidSignature()) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.email_invalid_signature', [], 'tk'),
            ], 403);
        }

        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.email_invalid_hash', [], 'tk'),
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'success',
                'message' => __('messages.email_already_verified', [], 'tk'),
            ], 200);
        }

        if ($user->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        return response()->json([
            'status' => 'success',
            'message' => __('messages.email_verified_success', [], 'tk'),
        ], 200);
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie(self::REFRESH_COOKIE_NAME);

        if (!$refreshToken) {
            return response()->json(['message' => __('messages.unauthorized')], 401);
        }

        $tokenModel = PersonalAccessToken::findToken($refreshToken);

        if (!$tokenModel || $tokenModel->expires_at?->isPast()) {
            return response()->json(['message' => __('messages.unauthorized')], 401);
        }

        /** @var User $user */
        $user = $tokenModel->tokenable;

        $tokenModel->delete();

        [$newAccessToken, $newCookie] = $this->issueTokens($user);

        return response()->json([
            'token' => $newAccessToken,
        ])->withCookie($newCookie);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user) {
            $currentAccessToken = $user->currentAccessToken();

            if ($currentAccessToken && property_exists($currentAccessToken, 'id')) {
                $user->tokens()->where('id', $currentAccessToken->id)->delete();
            }

            if ($refreshToken = $request->cookie(self::REFRESH_COOKIE_NAME)) {
                if ($tokenModel = PersonalAccessToken::findToken($refreshToken)) {
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

        $forgetCookie = cookie()->forget(self::REFRESH_COOKIE_NAME);

        return response()->json([
            'success' => true,
            'message' => __('messages.logout_success'),
        ])->withCookie($forgetCookie);
    }

    public function me(): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return response()->json(['message' => __('messages.unauthorized')], 401);
        }

        return response()->json($user);
    }

    public function user_activity(): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            $user->forceFill(['last_activity' => Carbon::now()])->saveQuietly();
        }

        return response()->json([
            'message' => 'Activity updated successfully',
            'status' => 'success',
        ], 200);
    }

    private function issueTokens(User $user): array
    {
        $accessExpiry = Carbon::now()->addMinutes(self::ACCESS_TOKEN_EXPIRY_MINUTES);
        $accessTokenResult = $user->createToken('access_token', ['*'], $accessExpiry);

        $refreshExpiry = Carbon::now()->addMinutes(self::REFRESH_TOKEN_EXPIRY_MINUTES);
        $refreshTokenResult = $user->createToken('refresh_token', ['issue-access-token'], $refreshExpiry);

        $cookie = cookie(
            self::REFRESH_COOKIE_NAME,
            $refreshTokenResult->plainTextToken,
            self::REFRESH_TOKEN_EXPIRY_MINUTES,
            '/',
            null,
            true,
            true,
            false,
            Cookie::SAMESITE_LAX
        );

        return [$accessTokenResult->plainTextToken, $cookie];
    }
}
