<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAbsoluteSessionLifetime
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $startedAt = $request->session()->get('auth.session_started_at');

        if (! is_int($startedAt)) {
            $request->session()->put('auth.session_started_at', now()->getTimestamp());

            return $next($request);
        }

        if ($startedAt <= now()->subSeconds((int) config('auth.session_absolute_timeout', 43200))->getTimestamp()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->withErrors([
                'email' => __('Your session expired. Please sign in again.'),
            ]);
        }

        return $next($request);
    }
}
