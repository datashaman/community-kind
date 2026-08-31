<?php

namespace App\Actions\Engagement;

use App\Enums\AudienceActivityType;
use App\Enums\ConsentDecision;
use App\Enums\DonationPaymentStatus;
use App\Enums\PartyContactType;
use App\Models\AudienceSegment;
use App\Models\Donation;
use App\Models\EventRegistration;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\PartyConsent;
use App\Models\PartyInterest;
use App\Models\VolunteerHourEntry;
use App\OrganisationContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EvaluateAudienceSegment
{
    public function __construct(private readonly OrganisationContext $context) {}

    /** @return Collection<int, covariant array{uuid: string, displayName: string, role: string, serviceAreas: list<string>, interests: list<string>, donationCount: int<0, max>, activityType: 'any'|'donation'|'event'|'volunteer', activityFrequency: int, activityValue: int|null, latestActivityAt: string|null, consentedAt: string}> */
    public function handle(AudienceSegment $segment): Collection
    {
        $this->context->ensureOwns($segment->organisation_id);
        $criteria = $this->criteria($segment->criteria);
        $contactType = $criteria['channel'] === 'email' ? PartyContactType::Email : PartyContactType::Telephone;
        $evaluatedAt = CarbonImmutable::instance(now());

        return Party::query()
            ->whereHas('businessRoles', fn ($query) => $query->where('role', $criteria['role']))
            ->whereHas('contactPoints', fn ($query) => $query->where('type', $contactType))
            ->with(['addresses', 'businessRoles', 'consents', 'contactPoints', 'donations.payments', 'eventRegistrations', 'interests', 'safeContactInstructions', 'volunteerHourEntries'])
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

                $activity = $this->activity($party, $criteria, $evaluatedAt);

                return $activity['frequency'] >= $criteria['minimum_frequency']
                    && ($criteria['minimum_value'] === null || ($activity['value'] !== null && $activity['value'] >= $criteria['minimum_value']));
            })
            ->map(fn (Party $party) => $this->supporterResult($party, $criteria, $evaluatedAt))
            ->values();
    }

    /**
     * @param  array{purpose: string, channel: string, role: string, campaign_source: string|null, activity_type: AudienceActivityType, recency_days: int|null, minimum_value: int|null}  $criteria
     * @return array{uuid: string, displayName: string, role: string, serviceAreas: list<string>, interests: list<string>, donationCount: int<0, max>, activityType: 'any'|'donation'|'event'|'volunteer', activityFrequency: int, activityValue: int|null, latestActivityAt: string|null, consentedAt: string}
     */
    private function supporterResult(Party $party, array $criteria, CarbonImmutable $evaluatedAt): array
    {
        $consent = $this->latestConsent($party, $criteria['purpose'], $criteria['channel']);
        $activity = $this->activity($party, $criteria, $evaluatedAt);

        return [
            'uuid' => $party->uuid,
            'displayName' => $party->display_name,
            'role' => $criteria['role'],
            'serviceAreas' => array_values($party->addresses->map(fn (PartyAddress $address): ?string => $address->service_area)->filter(fn (?string $area): bool => $area !== null)->unique()->all()),
            'interests' => array_values($party->interests->map(fn (PartyInterest $interest): string => $interest->label)->unique()->all()),
            'donationCount' => $this->eligibleDonations($party, $criteria['campaign_source'], $criteria['recency_days'], $evaluatedAt)->count(),
            'activityType' => $criteria['activity_type']->value,
            'activityFrequency' => $activity['frequency'],
            'activityValue' => $activity['value'],
            'latestActivityAt' => $activity['latest_at']?->toAtomString(),
            'consentedAt' => $consent?->occurred_at->toAtomString() ?? '',
        ];
    }

    /** @param array<string, mixed> $stored
     * @return array{purpose: string, channel: string, role: string, service_area: string|null, interest: string|null, donation_activity: bool, campaign_source: string|null, activity_type: AudienceActivityType, recency_days: int|null, minimum_frequency: int, minimum_value: int|null}
     */
    private function criteria(array $stored): array
    {
        $legacyDonationRequired = (bool) ($stored['donation_activity'] ?? false);

        return [
            'purpose' => (string) $stored['purpose'],
            'channel' => (string) $stored['channel'],
            'role' => (string) $stored['role'],
            'service_area' => isset($stored['service_area']) ? (string) $stored['service_area'] : null,
            'interest' => isset($stored['interest']) ? (string) $stored['interest'] : null,
            'donation_activity' => $legacyDonationRequired,
            'campaign_source' => isset($stored['campaign_source']) ? (string) $stored['campaign_source'] : null,
            'activity_type' => AudienceActivityType::from((string) ($stored['activity_type'] ?? ($legacyDonationRequired ? 'donation' : 'any'))),
            'recency_days' => isset($stored['recency_days']) ? (int) $stored['recency_days'] : null,
            'minimum_frequency' => (int) ($stored['minimum_frequency'] ?? ($legacyDonationRequired || isset($stored['campaign_source']) ? 1 : 0)),
            'minimum_value' => isset($stored['minimum_value']) ? (int) $stored['minimum_value'] : null,
        ];
    }

    /** @param array{activity_type: AudienceActivityType, campaign_source: string|null, recency_days: int|null, minimum_value: int|null} $criteria
     * @return array{frequency: int, value: int|null, latest_at: CarbonImmutable|null}
     */
    private function activity(Party $party, array $criteria, CarbonImmutable $evaluatedAt): array
    {
        $donations = $this->eligibleDonations($party, $criteria['campaign_source'], $criteria['recency_days'], $evaluatedAt)
            ->flatMap->payments
            ->filter(fn ($payment): bool => $payment->status === DonationPaymentStatus::Succeeded
                && $this->withinRecency($payment->settled_at ?? $payment->updated_at, $criteria['recency_days'], $evaluatedAt));
        $events = $party->eventRegistrations->filter(fn (EventRegistration $registration): bool => $registration->attended_at !== null && $this->withinRecency($registration->attended_at, $criteria['recency_days'], $evaluatedAt));
        $volunteerHours = $party->volunteerHourEntries->filter(fn (VolunteerHourEntry $entry): bool => $this->withinRecency($entry->occurred_at, $criteria['recency_days'], $evaluatedAt));

        return match ($criteria['activity_type']) {
            AudienceActivityType::Donation => ['frequency' => $donations->count(), 'value' => (int) $donations->sum('amount_minor'), 'latest_at' => $this->latestDate($donations->map(fn ($payment) => $payment->settled_at ?? $payment->updated_at))],
            AudienceActivityType::Event => ['frequency' => $events->count(), 'value' => $events->count(), 'latest_at' => $this->latestDate($events->pluck('attended_at'))],
            AudienceActivityType::Volunteer => ['frequency' => $volunteerHours->count(), 'value' => (int) $volunteerHours->sum('minutes'), 'latest_at' => $this->latestDate($volunteerHours->pluck('occurred_at'))],
            AudienceActivityType::Any => ['frequency' => $donations->count() + $events->count() + $volunteerHours->count(), 'value' => null, 'latest_at' => $this->latestDate(collect([...$donations->map(fn ($payment) => $payment->settled_at ?? $payment->updated_at), ...$events->pluck('attended_at'), ...$volunteerHours->pluck('occurred_at')]))],
        };
    }

    private function latestConsent(Party $party, string $purpose, string $channel): ?PartyConsent
    {
        return $party->consents
            ->filter(fn (PartyConsent $consent): bool => $consent->purpose->value === $purpose && $consent->channel->value === $channel)
            ->sortByDesc(fn (PartyConsent $consent): string => $consent->occurred_at->format('Y-m-d H:i:s.u').$consent->id)
            ->first();
    }

    /** @return Collection<int, Donation> */
    private function eligibleDonations(Party $party, ?string $campaignSource, ?int $recencyDays, CarbonImmutable $evaluatedAt): Collection
    {
        return $party->donations->filter(fn (Donation $donation): bool => ($campaignSource === null || $donation->source_code === $campaignSource)
            && $donation->payments->contains(fn ($payment): bool => $payment->status === DonationPaymentStatus::Succeeded && $this->withinRecency($payment->settled_at ?? $payment->updated_at, $recencyDays, $evaluatedAt)));
    }

    private function withinRecency(mixed $occurredAt, ?int $recencyDays, CarbonImmutable $evaluatedAt): bool
    {
        return $occurredAt !== null && ($recencyDays === null || CarbonImmutable::instance($occurredAt)->gte($evaluatedAt->subDays($recencyDays)));
    }

    /** @param Collection<int, mixed> $dates */
    private function latestDate(Collection $dates): ?CarbonImmutable
    {
        $latest = $dates->filter()->sortDesc()->first();

        return $latest === null ? null : CarbonImmutable::instance($latest);
    }
}
