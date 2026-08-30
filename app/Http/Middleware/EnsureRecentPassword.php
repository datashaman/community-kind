<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;

class EnsureRecentPassword extends RequirePassword
{
    public function handle($request, $next, $redirectToRoute = null, $passwordTimeoutSeconds = null): mixed
    {
        if (
            $request->isMethodSafe()
            || ! $this->shouldConfirmPassword($request, $passwordTimeoutSeconds)
            || $request->expectsJson()
        ) {
            return parent::handle($request, $next, $redirectToRoute, $passwordTimeoutSeconds);
        }

        $request->session()->put('auth.security_return_url', $this->safeReturnUrl($request));

        return to_route($redirectToRoute ?: 'password.confirm');
    }

    private function safeReturnUrl(Request $request): string
    {
        $candidate = url()->previous();
        $origin = $request->getSchemeAndHttpHost();

        return $candidate === $origin || str_starts_with($candidate, $origin.'/')
            ? $candidate
            : route('security.edit');
    }
}
