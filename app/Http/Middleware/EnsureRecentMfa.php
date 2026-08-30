<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecentMfa
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('auth.mfa_confirmed_at');
        $timeout = (int) config('auth.mfa_timeout', 900);

        if (! is_int($confirmedAt) || $confirmedAt < now()->subSeconds($timeout)->getTimestamp()) {
            $request->session()->put('url.intended', $request->fullUrl());

            return to_route('mfa.confirm');
        }

        return $next($request);
    }
}
