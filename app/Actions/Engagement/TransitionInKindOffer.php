<?php

namespace App\Actions\Engagement;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\InKindOfferStatus;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Models\InKindOffer;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class TransitionInKindOffer
{
    public function __construct(private readonly OrganisationContext $context, private readonly RecordPartyTimelineEvent $recordTimeline, private readonly RecordTenantAuditEvent $recordAudit) {}

    public function handle(InKindOffer $offer, InKindOfferStatus $to, ?string $outcome, User $actor): InKindOffer
    {
        $this->context->ensureOwns($offer->organisation_id);

        return DB::transaction(function () use ($actor, $offer, $outcome, $to): InKindOffer {
            $locked = InKindOffer::query()->with('party')->lockForUpdate()->findOrFail($offer->id);
            $from = $locked->status;
            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition in-kind offer from {$from->value} to {$to->value}.");
            }
            if (in_array($to, [InKindOfferStatus::Fulfilled, InKindOfferStatus::UnableToFulfil], true) && blank($outcome)) {
                throw new LogicException('A fulfilment outcome is required.');
            }
            $locked->update(['status' => $to, 'fulfilment_outcome' => $outcome, 'fulfilled_at' => $to === InKindOfferStatus::Fulfilled ? now() : null, 'version' => $locked->version + 1, 'transitioned_by_user_id' => $actor->id]);
            $this->recordTimeline->handle($locked->party, PartyTimelineEventType::InKindOfferTransitioned, "In-kind offer changed from {$from->value} to {$to->value}.", $actor, 'in_kind_offer', $locked->id, ['status' => $to->value]);
            $this->recordAudit->handle($locked->party->organisation, TenantAuditEventType::InKindOfferTransitioned, 'in_kind_offer', $locked->id, ['offer_id' => $locked->id, 'from_status' => $from->value, 'to_status' => $to->value], $actor);

            return $locked->refresh();
        });
    }
}
