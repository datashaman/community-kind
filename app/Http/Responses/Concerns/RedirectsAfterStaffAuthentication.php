<?php

namespace App\Http\Responses\Concerns;

use App\Actions\Teams\AcceptTeamInvitation;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait RedirectsAfterStaffAuthentication
{
    use RedirectsToCurrentTeam;

    protected function staffAuthenticationResponse(
        Request $request,
        string $fallback,
        AcceptTeamInvitation $acceptTeamInvitation,
    ): Response {
        $user = $request->user();

        abort_if(! $user, 403);

        if (! $user->hasVerifiedEmail()) {
            return to_route('verification.notice');
        }

        $this->acceptPendingInvitation($request, $acceptTeamInvitation);

        if (! $user->hasEnabledTwoFactorAuthentication() || ! $user->hasAcknowledgedRecoveryCodes()) {
            return to_route('security.edit');
        }

        return redirect()->intended($this->redirectPathForCurrentTeam($request, $fallback));
    }

    private function acceptPendingInvitation(Request $request, AcceptTeamInvitation $acceptTeamInvitation): void
    {
        $invitationId = $request->session()->get('auth.pending_team_invitation_id');
        $invitation = is_numeric($invitationId) ? TeamInvitation::find($invitationId) : null;

        if (! $invitation?->isPending() || ! $invitation->isFor($request->user())) {
            $request->session()->forget('auth.pending_team_invitation_id');

            return;
        }

        $acceptTeamInvitation->handle($request->user(), $invitation);
        $request->session()->forget('auth.pending_team_invitation_id');
    }
}
