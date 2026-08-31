<?php

namespace App\Actions\Volunteering;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Enums\VolunteerCredentialStatus;
use App\Models\Organisation;
use App\Models\VolunteerCredential;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

final class ExpireVolunteerCredentials
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordPartyTimelineEvent $recordTimeline,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(Organisation $organisation): int
    {
        $this->context->ensureOwns($organisation->id);
        $expired = 0;

        VolunteerCredential::query()
            ->where('status', VolunteerCredentialStatus::Verified)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('id')
            ->each(function (string $credentialId) use (&$expired, $organisation): void {
                DB::transaction(function () use (&$expired, $credentialId, $organisation): void {
                    $credential = VolunteerCredential::query()->with('party')->lockForUpdate()->findOrFail($credentialId);
                    if ($credential->status !== VolunteerCredentialStatus::Verified || $credential->expires_at?->isFuture()) {
                        return;
                    }

                    $credential->update(['status' => VolunteerCredentialStatus::Expired]);
                    $this->recordTimeline->handle($credential->party, PartyTimelineEventType::VolunteerCredentialExpired, "{$credential->type} credential expired.", subjectType: 'volunteer_credential', subjectId: $credential->id, metadata: ['status' => VolunteerCredentialStatus::Expired->value]);
                    $this->recordAudit->handle($organisation, TenantAuditEventType::VolunteerCredentialExpired, 'volunteer_credential', $credential->id, ['credential_id' => $credential->id, 'application_id' => $credential->volunteer_application_id]);
                    $expired++;
                });
            });

        return $expired;
    }
}
