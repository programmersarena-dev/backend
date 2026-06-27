<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;

class SetLocalization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = ['tk', 'en', 'ru'];
        $locale = null;

        if ($request->user('sanctum')) {
            $locale = $request->user('sanctum')->locale;
        }

        if (!$locale || !in_array($locale, $supportedLocales)) {
            $locale = $request->header('Accept-Language', 'tk');

            $locale = substr($locale, 0, 2);

            if (!in_array($locale, $supportedLocales)) {
                $locale = config('app.fallback_locale', 'tk');
            }
        }

        App::setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
