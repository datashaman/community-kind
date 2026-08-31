<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffSecurityRequirements
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('demo_sandbox.enabled') && $request->session()->has('demo_sandbox_pair_id')) {
            return $next($request);
        }

        if (! config('auth.mfa_required')) {
            return $next($request);
        }

        $user = $request->user();

        abort_if($user === null, 403);

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return to_route('security.edit', ['required' => 'mfa']);
        }

        if (! $user->hasAcknowledgedRecoveryCodes()) {
            return to_route('security.edit', ['required' => 'recovery-codes']);
        }

        return $next($request);
    }
}
