<?php

namespace App\Actions\Portal;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyConsent;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\TenantAuditEventType;
use App\Models\PartyConsent;
use App\Models\PortalAccessGrant;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

final class UpdatePortalConsentPreferences
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordPartyConsent $recordConsent,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    /** @param list<ConsentChannel> $enabledChannels */
    public function handle(PortalAccessGrant $grant, array $enabledChannels): void
    {
        $this->context->ensureOwns($grant->organisation_id);
        abort_unless($grant->hasActiveAccess(), 410);

        DB::transaction(function () use ($enabledChannels, $grant): void {
            $party = $grant->personParty()->lockForUpdate()->firstOrFail();
            $changedChannels = [];

            foreach ([ConsentChannel::Email, ConsentChannel::Sms, ConsentChannel::Telephone] as $channel) {
                $latest = PartyConsent::query()
                    ->where('party_id', $party->id)
                    ->where('purpose', ConsentPurpose::SupporterUpdates)
                    ->where('channel', $channel)
                    ->latest('occurred_at')
                    ->latest('id')
                    ->first();
                $enabled = in_array($channel, $enabledChannels, true);

                if (($latest?->decision === ConsentDecision::Granted) === $enabled) {
                    continue;
                }

                $decision = $enabled
                    ? ConsentDecision::Granted
                    : ($latest?->decision === ConsentDecision::Granted
                        ? ConsentDecision::Withdrawn
                        : ConsentDecision::Suppressed);

                $this->recordConsent->handle($party, [
                    'purpose' => ConsentPurpose::SupporterUpdates,
                    'channel' => $channel,
                    'decision' => $decision,
                    'wording_version' => 'supporter-portal-v1',
                    'wording' => 'I choose whether to receive supporter updates through this channel.',
                    'source' => 'supporter_portal',
                    'occurred_at' => now()->toAtomString(),
                ], $grant->user);
                $changedChannels[] = $channel->value;
            }

            if ($changedChannels !== []) {
                $this->recordAudit->handle(
                    $party->organisation,
                    TenantAuditEventType::SupporterConsentPreferencesUpdated,
                    'party',
                    $party->uuid,
                    ['party_uuid' => $party->uuid, 'channels' => $changedChannels],
                    $grant->user,
                );
            }
        });
    }
}
