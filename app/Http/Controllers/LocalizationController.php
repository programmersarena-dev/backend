<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

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
     * @return \Illuminate\Http\JsonResponse
     */
    public function setLocale(Request $request): JsonResponse
    {
        $request->validate([
            'lang' => ['required', 'string', 'in:tk,en,ru'],
        ]);

        $locale = $request->input('lang');
        App::setLocale($locale);

        if ($user = $request->user('sanctum')) {
            $user->update(['locale' => $locale]);
        }

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'message' => __('messages.locale_updated')
        ], 200);
    }
}
