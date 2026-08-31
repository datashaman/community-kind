<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Actions\Portal\RevokePortalAccessGrant;
use App\Enums\PartyKind;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\PortalAccessGrant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PortalAccessController extends Controller
{
    public function use(
        Request $request,
        string $publicOrganisation,
        string $token,
        RecordPartyTimelineEvent $recordTimeline,
        RecordTenantAuditEvent $recordAudit,
    ): RedirectResponse {
        $organisation = $request->attributes->get('public_organisation');
        abort_unless($organisation instanceof Organisation, 404);

        $grant = DB::transaction(function () use ($organisation, $recordAudit, $recordTimeline, $token): PortalAccessGrant {
            $grant = PortalAccessGrant::query()
                ->withoutGlobalScopes()
                ->with(['organisation', 'personParty', 'user'])
                ->where('organisation_id', $organisation->id)
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $grant->revoked_at === null
                && $grant->token_used_at === null
                && $grant->token_expires_at->isFuture()
                && $grant->user->hasVerifiedEmail()
                && $grant->personParty->kind === PartyKind::Person
                && ! $grant->personParty->trashed(),
                410,
                'This portal link is no longer available.',
            );

            $grant->update(['verified_at' => now(), 'token_used_at' => now()]);
            $recordTimeline->handle(
                $grant->personParty,
                PartyTimelineEventType::PortalAccessChanged,
                'Supporter verified their portal access.',
                $grant->user,
                'portal_access_grant',
                $grant->id,
                ['status' => 'verified'],
            );
            $recordAudit->handle(
                $grant->organisation,
                TenantAuditEventType::PortalAccessVerified,
                'portal_access_grant',
                $grant->id,
                [
                    'grant_id' => $grant->id,
                    'party_uuid' => $grant->personParty->uuid,
                    'user_id' => $grant->user_id,
                ],
                $grant->user,
            );

            return $grant;
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($grant->user);
        $request->session()->regenerate();
        $request->session()->put('portal_access_grant_id', $grant->id);
        $request->session()->put('portal_access_version', $grant->access_version);

        return to_route('portal.show', ['public_organisation' => $organisation->slug]);
    }

    public function destroy(
        Request $request,
        string $publicOrganisation,
        RevokePortalAccessGrant $revokePortalAccessGrant,
    ): RedirectResponse {
        $organisation = $request->attributes->get('public_organisation');
        $grant = $request->attributes->get('portal_access_grant');
        abort_unless($organisation instanceof Organisation && $grant instanceof PortalAccessGrant, 404);

        $revokePortalAccessGrant->handle($grant);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('public.organisations.show', ['public_organisation' => $organisation->slug]);
    }
}
