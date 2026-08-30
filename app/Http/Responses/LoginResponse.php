<?php

namespace App\Http\Responses;

use App\Actions\Organisations\AcceptOrganisationInvitation;
use App\Http\Responses\Concerns\RedirectsAfterStaffAuthentication;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    use RedirectsAfterStaffAuthentication;

    public function __construct(private AcceptOrganisationInvitation $acceptOrganisationInvitation) {}

    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : $this->staffAuthenticationResponse($request, Fortify::redirects('login'), $this->acceptOrganisationInvitation);
    }
}
