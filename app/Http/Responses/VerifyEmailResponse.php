<?php

namespace App\Http\Responses;

use App\Actions\Organisations\AcceptOrganisationInvitation;
use App\Models\OrganisationInvitation;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function __construct(private AcceptOrganisationInvitation $acceptInvitation) {}

    public function toResponse($request): Response
    {
        $invitationId = $request->session()->pull('auth.pending_organisation_invitation_id');
        $invitation = is_numeric($invitationId) ? OrganisationInvitation::find($invitationId) : null;

        if ($invitation?->isPending() && $invitation->isFor($request->user())) {
            $this->acceptInvitation->handle($request->user(), $invitation);
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : to_route('security.edit', ['verified' => 1]);
    }
}
