<?php

namespace App\Http\Responses\Concerns;

use App\Actions\Organisations\AcceptOrganisationInvitation;
use App\Models\OrganisationInvitation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait RedirectsAfterStaffAuthentication
{
    use RedirectsToCurrentOrganisation;

    protected function staffAuthenticationResponse(
        Request $request,
        string $fallback,
        AcceptOrganisationInvitation $acceptOrganisationInvitation,
    ): Response {
        $user = $request->user();

        abort_if(! $user, 403);

        if (! $user->hasVerifiedEmail()) {
            return to_route('verification.notice');
        }

        $this->acceptPendingInvitation($request, $acceptOrganisationInvitation);

        if (config('auth.mfa_required') && ! $user->hasEnabledTwoFactorAuthentication()) {
            return to_route('security.edit', ['required' => 'mfa']);
        }

        if (config('auth.mfa_required') && ! $user->hasAcknowledgedRecoveryCodes()) {
            return to_route('security.edit', ['required' => 'recovery-codes']);
        }

        return redirect()->intended($this->redirectPathForCurrentOrganisation($request, $fallback));
    }

    private function acceptPendingInvitation(Request $request, AcceptOrganisationInvitation $acceptOrganisationInvitation): void
    {
        $invitationId = $request->session()->get('auth.pending_organisation_invitation_id');
        $invitation = is_numeric($invitationId) ? OrganisationInvitation::find($invitationId) : null;

        if (! $invitation?->isPending() || ! $invitation->isFor($request->user())) {
            $request->session()->forget('auth.pending_organisation_invitation_id');

            return;
        }

        $acceptOrganisationInvitation->handle($request->user(), $invitation);
        $request->session()->forget('auth.pending_organisation_invitation_id');
    }
}
