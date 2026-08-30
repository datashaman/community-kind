<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse as PasswordConfirmedResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class PasswordConfirmedResponse implements PasswordConfirmedResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        $returnUrl = $request->session()->pull('auth.security_return_url');

        return is_string($returnUrl)
            ? redirect()->to($returnUrl)
            : redirect()->intended(Fortify::redirects('password-confirmation'));
    }
}
