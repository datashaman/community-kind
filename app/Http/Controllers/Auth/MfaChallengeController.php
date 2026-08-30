<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmMfaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class MfaChallengeController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/confirm-mfa');
    }

    public function store(
        ConfirmMfaRequest $request,
        TwoFactorAuthenticationProvider $provider,
    ): RedirectResponse {
        $secret = Fortify::currentEncrypter()->decrypt($request->user()->two_factor_secret);

        if (! $provider->verify($secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => __('The provided two-factor authentication code was invalid.'),
            ]);
        }

        $request->session()->put('auth.mfa_confirmed_at', now()->getTimestamp());

        $returnUrl = $request->session()->pull('auth.security_return_url');

        return is_string($returnUrl)
            ? redirect()->to($returnUrl)
            : redirect()->intended(route('security.edit'));
    }
}
