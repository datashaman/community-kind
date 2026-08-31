<?php

namespace App\Actions\Engagement;

use App\Enums\ConsentDecision;
use App\Enums\DonationPaymentStatus;
use App\Enums\PartyContactType;
use App\Models\AudienceSegment;
use App\Models\Donation;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\PartyConsent;
use App\Models\PartyInterest;
use App\OrganisationContext;
use Illuminate\Support\Collection;

class EvaluateAudienceSegment
{
    public function __construct(private readonly OrganisationContext $context) {}

    /** @return Collection<int, array{uuid: string, displayName: string, role: string, serviceAreas: array<int, string>, interests: array<int, string>, donationCount: int<0, max>, consentedAt: string}> */
    public function handle(AudienceSegment $segment): Collection
    {
        $this->context->ensureOwns($segment->organisation_id);
        $criteria = $segment->criteria;
        $contactType = $criteria['channel'] === 'email' ? PartyContactType::Email : PartyContactType::Telephone;
        $evaluatedAt = now();

        return Party::query()
            ->whereHas('businessRoles', fn ($query) => $query->where('role', $criteria['role']))
            ->whereHas('contactPoints', fn ($query) => $query->where('type', $contactType))
            ->with(['addresses', 'businessRoles', 'consents', 'contactPoints', 'donations.payments', 'interests', 'safeContactInstructions'])
            ->orderBy('display_name')
            ->orderBy('id')
            ->get()
            ->filter(function (Party $party) use ($criteria, $evaluatedAt): bool {
                $latestConsent = $this->latestConsent($party, $criteria['purpose'], $criteria['channel']);

                if ($latestConsent?->decision !== ConsentDecision::Granted) {
                    return false;
                }

                if ($party->safeContactInstructions->contains(fn ($instruction): bool => $instruction->effective_at->lte($evaluatedAt) && ($instruction->ended_at === null || $instruction->ended_at->gt($evaluatedAt)))) {
                    return false;
                }

                if ($criteria['service_area'] !== null && ! $party->addresses->contains('service_area', $criteria['service_area'])) {
                    return false;
                }

                if ($criteria['interest'] !== null && ! $party->interests->contains('slug', $criteria['interest'])) {
                    return false;
                }

                return (! $criteria['donation_activity'] && $criteria['campaign_source'] === null)
                    || $this->eligibleDonations($party, $criteria['campaign_source'])->isNotEmpty();
            })
            ->map(function (Party $party) use ($criteria): array {
                $consent = $this->latestConsent($party, $criteria['purpose'], $criteria['channel']);
                $donations = $this->eligibleDonations($party, $criteria['campaign_source']);

                return [
                    'uuid' => $party->uuid,
                    'displayName' => $party->display_name,
                    'role' => $criteria['role'],
                    'serviceAreas' => $party->addresses->map(fn (PartyAddress $address): ?string => $address->service_area)->filter(fn (?string $area): bool => $area !== null)->unique()->values()->all(),
                    'interests' => $party->interests->map(fn (PartyInterest $interest): string => $interest->label)->unique()->values()->all(),
                    'donationCount' => $donations->count(),
                    'consentedAt' => $consent?->occurred_at->toAtomString() ?? '',
                ];
            })
            ->values();
    }

    private function latestConsent(Party $party, string $purpose, string $channel): ?PartyConsent
    {
        return $party->consents
            ->filter(fn (PartyConsent $consent): bool => $consent->purpose->value === $purpose && $consent->channel->value === $channel)
            ->sortByDesc(fn (PartyConsent $consent): string => $consent->occurred_at->format('Y-m-d H:i:s.u').$consent->id)
            ->first();
    }

    /** @return Collection<int, Donation> */
    private function eligibleDonations(Party $party, ?string $campaignSource): Collection
    {
        return $party->donations->filter(fn ($donation): bool => ($campaignSource === null || $donation->source_code === $campaignSource)
            && $donation->payments->contains('status', DonationPaymentStatus::Succeeded));
    }
}
