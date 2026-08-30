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
            $request->session()->put('auth.security_return_url', $this->safeReturnUrl($request));

            return to_route('mfa.confirm');
        }

        return $next($request);
    }

    private function safeReturnUrl(Request $request): string
    {
        if ($request->isMethodSafe()) {
            return $request->fullUrl();
        }

        $candidate = url()->previous();
        $origin = $request->getSchemeAndHttpHost();

        return $candidate === $origin || str_starts_with($candidate, $origin.'/')
            ? $candidate
            : route('security.edit');
    }
}
