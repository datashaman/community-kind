<?php

namespace App\Actions\Engagement;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyConsent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\InKindOfferStatus;
use App\Enums\PartyBusinessRole;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Models\InKindOffer;
use App\Models\Organisation;
use App\Models\PartyRole;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

final class RecordInKindOffer
{
    public function __construct(private readonly OrganisationContext $context, private readonly ResolvePublicPerson $resolvePerson, private readonly RecordPartyConsent $recordConsent, private readonly RecordPartyTimelineEvent $recordTimeline, private readonly RecordTenantAuditEvent $recordAudit) {}

    /** @param array{name: string, email: string, category: string, description: string, quantity: float, unit: string, estimated_value_minor: int|null, currency: string|null, condition: string, consent_email: bool} $attributes */
    public function handle(Organisation $organisation, array $attributes): InKindOffer
    {
        $this->context->ensureOwns($organisation->id);

        return DB::transaction(function () use ($attributes, $organisation): InKindOffer {
            $party = $this->resolvePerson->handle($attributes['name'], $attributes['email']);
            PartyRole::query()->firstOrCreate(['organisation_id' => $organisation->id, 'party_id' => $party->id, 'role' => PartyBusinessRole::InKindContributor]);
            $offer = InKindOffer::query()->create(['organisation_id' => $organisation->id, 'party_id' => $party->id, ...collect($attributes)->except(['name', 'email', 'consent_email'])->all(), 'status' => InKindOfferStatus::Offered, 'version' => 1, 'offered_at' => now()]);
            $this->recordConsent->handle($party, ['purpose' => ConsentPurpose::SupporterUpdates, 'channel' => ConsentChannel::Email, 'decision' => $attributes['consent_email'] ? ConsentDecision::Granted : ConsentDecision::Suppressed, 'wording_version' => 'in-kind-offer-v1', 'wording' => 'I choose whether to receive in-kind fulfilment follow-up and supporter updates by email.', 'source' => 'public_in_kind_offer', 'occurred_at' => now()->toAtomString()], null);
            $this->recordTimeline->handle($party, PartyTimelineEventType::InKindOfferTransitioned, 'In-kind offer recorded.', subjectType: 'in_kind_offer', subjectId: $offer->id, metadata: ['status' => InKindOfferStatus::Offered->value]);
            $this->recordAudit->handle($organisation, TenantAuditEventType::InKindOfferTransitioned, 'in_kind_offer', $offer->id, ['offer_id' => $offer->id, 'from_status' => 'unoffered', 'to_status' => InKindOfferStatus::Offered->value]);

            return $offer;
        });
    }
}
