<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenExpiry
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('auth/refresh')) {
            return $next($request);
        }

        $authHeader = $request->header('Authorization');

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);

            $tokenModel = PersonalAccessToken::findToken($token);

            if ($tokenModel && $tokenModel->expires_at?->isPast()) {
                return response()->json(['message' => __('messages.unauthorized')], 401);
            }
        }

        return $next($request);
    }
}
