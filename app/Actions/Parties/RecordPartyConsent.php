<?php

namespace App\Actions\Parties;

use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\PartyTimelineEventType;
use App\Models\Party;
use App\Models\PartyConsent;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class RecordPartyConsent
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly RecordPartyTimelineEvent $recordTimelineEvent,
    ) {}

    /** @param array{purpose: ConsentPurpose, channel?: ConsentChannel, decision: ConsentDecision, wording_version: string, wording: string, source: string, occurred_at: string} $attributes */
    public function handle(Party $party, array $attributes, ?User $actor): PartyConsent
    {
        $this->organisationContext->ensureOwns($party->organisation_id);

        return DB::transaction(function () use ($party, $attributes, $actor): PartyConsent {
            $party = Party::query()->lockForUpdate()->findOrFail($party->id);
            $channel = $attributes['channel'] ?? ConsentChannel::NotApplicable;
            $latest = PartyConsent::query()
                ->where('party_id', $party->id)
                ->where('purpose', $attributes['purpose'])
                ->where('channel', $channel)
                ->latest('occurred_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($attributes['decision'] === ConsentDecision::Withdrawn
                && ! $this->isGranted($latest)) {
                throw new LogicException('Consent can only be withdrawn after it has been granted.');
            }

            $consent = PartyConsent::query()->create([
                'organisation_id' => $party->organisation_id,
                'party_id' => $party->id,
                ...$attributes,
                'channel' => $channel,
                'supersedes_id' => $latest?->id,
                'recorded_by_user_id' => $actor?->id,
            ]);
            $this->recordTimelineEvent->handle(
                $party,
                PartyTimelineEventType::ConsentRecorded,
                ucfirst($attributes['purpose']->value).' consent '.$attributes['decision']->value,
                $actor,
                'party_consent',
                $consent->id,
                [
                    'purpose' => $attributes['purpose']->value,
                    'decision' => $attributes['decision']->value,
                    'wording_version' => $attributes['wording_version'],
                ],
            );

            return $consent;
        });
    }

    private function isGranted(?PartyConsent $consent): bool
    {
        return $consent !== null && $consent->decision === ConsentDecision::Granted;
    }
}
