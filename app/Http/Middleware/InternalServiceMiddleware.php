<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalServiceMiddleware
{
    protected $name = 'internal';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Judge-Token') !== config('services.judge.token')) {
            abort(401);
        }

        return $next($request);
    }
}