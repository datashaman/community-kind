<?php

namespace App\Http\Responses;

use App\Actions\Teams\AcceptTeamInvitation;
use App\Http\Responses\Concerns\RedirectsAfterStaffAuthentication;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    use RedirectsAfterStaffAuthentication;

    public function __construct(private AcceptTeamInvitation $acceptTeamInvitation) {}

    public function toResponse($request): Response
    {
        $request->session()->put('auth.mfa_confirmed_at', now()->unix());

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : $this->staffAuthenticationResponse($request, Fortify::redirects('login'), $this->acceptTeamInvitation);
    }
}
