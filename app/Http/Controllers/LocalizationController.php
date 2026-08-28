<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class LocalizationController extends Controller
{
    /**
     * Supported locales within ProgrammersArena.
     *
     * @var array<string>
     */
    protected array $supportedLocales = ['tk', 'en', 'ru'];

    /**
     * Update the application locale dynamically or validate client-side switches.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \JsonResponse
     */
    public function setLocale(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => ['required', 'string', 'in:tk,en,ru'],
        ]);

        $locale = $request->input('lang');
        App::setLocale($locale);

        $user = $request->user('sanctum');

        if (!$user) {
            $authHeader = $request->header('Authorization');

            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);

                $tokenModel = PersonalAccessToken::findToken($token);

                if ($tokenModel && !$tokenModel->expires_at?->isPast()) {
                    $user = $tokenModel->tokenable;
                }
            }
        }

        if ($user) {
            $user->update(['locale' => $locale]);
        }

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'message' => __('messages.locale_updated')
        ], 200);
    }
}
