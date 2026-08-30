<?php

namespace App\Http\Responses;

use App\Models\OrganisationInvitation;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): Response
    {
        $invitation = OrganisationInvitation::findByToken($request->string('invitation')->toString());

        if ($invitation?->isPending()) {
            $request->session()->put('auth.pending_organisation_invitation_id', $invitation->id);
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 201)
            : to_route('verification.notice');
    }
}
