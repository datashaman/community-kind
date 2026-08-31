<?php

namespace App\Actions\Portal;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Models\PortalAccessGrant;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

final class RevokePortalAccessGrant
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordPartyTimelineEvent $recordTimeline,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(PortalAccessGrant $grant): void
    {
        $this->context->ensureOwns($grant->organisation_id);

        DB::transaction(function () use ($grant): void {
            $locked = PortalAccessGrant::query()->with(['organisation', 'personParty', 'user'])
                ->whereKey($grant->id)
                ->where('user_id', $grant->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->revoked_at !== null) {
                return;
            }

            $locked->update([
                'revoked_at' => now(),
                'revoked_by_user_id' => $locked->user_id,
                'access_version' => $locked->access_version + 1,
            ]);
            $this->recordTimeline->handle(
                $locked->personParty,
                PartyTimelineEventType::PortalAccessChanged,
                'Supporter revoked their portal access.',
                $locked->user,
                'portal_access_grant',
                $locked->id,
                ['status' => 'revoked'],
            );
            $this->recordAudit->handle(
                $locked->organisation,
                TenantAuditEventType::PortalAccessRevoked,
                'portal_access_grant',
                $locked->id,
                [
                    'grant_id' => $locked->id,
                    'party_uuid' => $locked->personParty->uuid,
                    'user_id' => $locked->user_id,
                ],
                $locked->user,
            );
        });
    }
}
