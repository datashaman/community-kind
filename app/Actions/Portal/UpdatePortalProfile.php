<?php

namespace App\Actions\Portal;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Actions\Parties\SyncPartyContacts;
use App\Enums\PartyContactType;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Models\Party;
use App\Models\PartyContactPoint;
use App\Models\PortalAccessGrant;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

final class UpdatePortalProfile
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly SyncPartyContacts $syncContacts,
        private readonly RecordPartyTimelineEvent $recordTimeline,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    /** @param array{display_name: string, email: string|null, telephone: string|null} $attributes */
    public function handle(PortalAccessGrant $grant, array $attributes): Party
    {
        $this->context->ensureOwns($grant->organisation_id);
        abort_unless($grant->hasActiveAccess(), 410);

        return DB::transaction(function () use ($attributes, $grant): Party {
            $party = Party::query()->lockForUpdate()->findOrFail($grant->person_party_id);
            $changedFields = [];

            if ($party->display_name !== $attributes['display_name']) {
                $party->update(['display_name' => $attributes['display_name']]);
                $changedFields[] = 'display_name';
            }

            foreach ([PartyContactType::Email, PartyContactType::Telephone] as $type) {
                $current = PartyContactPoint::query()
                    ->where('party_id', $party->id)
                    ->where('type', $type)
                    ->first()?->encrypted_value->reveal();
                $next = $attributes[$type === PartyContactType::Email ? 'email' : 'telephone'];

                if ($current !== $next) {
                    $changedFields[] = $type->value;
                }
            }

            $this->syncContacts->handle($party, [
                'email' => $attributes['email'],
                'telephone' => $attributes['telephone'],
            ]);

            if ($changedFields !== []) {
                $this->recordTimeline->handle(
                    $party,
                    PartyTimelineEventType::ProfileUpdated,
                    'Supporter updated their portal profile.',
                    $grant->user,
                    'portal_access_grant',
                    $grant->id,
                    ['changed_fields' => implode(',', $changedFields)],
                );
                $this->recordAudit->handle(
                    $party->organisation,
                    TenantAuditEventType::SupporterProfileUpdated,
                    'party',
                    $party->uuid,
                    ['party_uuid' => $party->uuid, 'changed_fields' => $changedFields],
                    $grant->user,
                );
            }

            return $party->refresh();
        });
    }
}
