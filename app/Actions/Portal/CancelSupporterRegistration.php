<?php

namespace App\Actions\Portal;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\PartyTimelineEventType;
use App\Enums\SupporterRegistrationStatus;
use App\Enums\TenantAuditEventType;
use App\Models\PortalAccessGrant;
use App\Models\SupporterRegistration;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

final class CancelSupporterRegistration
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordPartyTimelineEvent $recordTimeline,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(PortalAccessGrant $grant, SupporterRegistration $registration): SupporterRegistration
    {
        $this->context->ensureOwns($grant->organisation_id);
        abort_unless($grant->hasActiveAccess(), 410);

        return DB::transaction(function () use ($grant, $registration): SupporterRegistration {
            $locked = SupporterRegistration::query()
                ->whereKey($registration->id)
                ->where('organisation_id', $grant->organisation_id)
                ->where('party_id', $grant->person_party_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($locked->status->canCancel(), 422, 'This registration can no longer be cancelled.');

            $locked->update([
                'status' => SupporterRegistrationStatus::Cancelled,
                'version' => $locked->version + 1,
                'cancelled_at' => now(),
            ]);
            $this->recordTimeline->handle(
                $grant->personParty,
                PartyTimelineEventType::SupporterRegistrationTransitioned,
                "Supporter cancelled {$locked->title}.",
                $grant->user,
                'supporter_registration',
                $locked->id,
                ['status' => SupporterRegistrationStatus::Cancelled->value],
            );
            $this->recordAudit->handle(
                $grant->organisation,
                TenantAuditEventType::SupporterRegistrationCancelled,
                'supporter_registration',
                $locked->id,
                [
                    'registration_id' => $locked->id,
                    'party_uuid' => $grant->personParty->uuid,
                    'kind' => $locked->kind->value,
                ],
                $grant->user,
            );

            return $locked->refresh();
        });
    }
}
