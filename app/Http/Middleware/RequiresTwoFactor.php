<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->error('Unauthenticated.', 401);
        }

        if ($user->two_factor_enabled && ! $request->user()->tokenCan('2fa')) {
            return response()->error('Two-factor authentication verification required.', 403);
        }

        return $next($request);
    }
}
