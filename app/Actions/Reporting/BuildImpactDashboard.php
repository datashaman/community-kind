<?php

namespace App\Actions\Reporting;

use App\Data\Demo\ScenarioCatalog;
use App\Enums\CaseMetricCode;
use App\Enums\DonationPaymentStatus;
use App\Enums\OrganisationRole;
use App\Enums\PartyBusinessRole;
use App\Enums\SupporterJourneyEventType;
use App\Models\CaseInteraction;
use App\Models\Donation;
use App\Models\DonationPaymentEvent;
use App\Models\DonationRefund;
use App\Models\FundraisingCampaign;
use App\Models\IntakeRequest;
use App\Models\MetricEvent;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\Program;
use App\Models\SupporterJourneyEvent;
use App\Models\User;
use App\Reporting\MetricRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BuildImpactDashboard
{
    public function __construct(private readonly MetricRegistry $registry) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, Organisation $organisation, array $filters): array
    {
        $role = $user->organisationRole($organisation);
        if ($role === OrganisationRole::ProgramManager) {
            $allowedProgramIds = Program::query()->get()->filter(fn (Program $program): bool => $user->hasProgramAccess($program))->pluck('id');
            $filters['_program_ids'] = filled($filters['program_id'] ?? null)
                ? $allowedProgramIds->intersect([(int) $filters['program_id']])->values()->all()
                : $allowedProgramIds->values()->all();
        }
        $context = $this->reportingContext($organisation);
        [$start, $end] = $this->period($filters, $context['timezone']);
        $duration = $start->diffInSeconds($end);
        $priorEnd = $start;
        $priorStart = $start->subSeconds($duration);
        $definitions = $this->registry->forRole($role);
        $sensitiveSlice = filled($filters['area'] ?? null) || filled($filters['location'] ?? null) || filled($filters['cohort'] ?? null);

        $metrics = collect($definitions)->map(function (array $definition) use ($end, $filters, $priorEnd, $priorStart, $sensitiveSlice, $start): array {
            $unsupported = array_diff($this->activeDimensions($filters), $definition['dimensions']);
            $current = $unsupported === [] ? $this->calculate($definition['id'], $start, $end, $filters) : ['value' => null, 'sampleSize' => 0];
            $prior = $unsupported === [] ? $this->calculate($definition['id'], $priorStart, $priorEnd, $filters) : ['value' => null, 'sampleSize' => 0];
            $suppressed = $sensitiveSlice && $current['sampleSize'] > 0 && $current['sampleSize'] < config('reporting.minimum_cohort');
            $availability = $suppressed ? 'suppressed' : ($current['value'] === null ? 'unavailable' : 'available');

            return [
                'definition' => $definition,
                'value' => $availability === 'available' ? $current['value'] : null,
                'availability' => $availability,
                'sampleSize' => $availability === 'suppressed' ? null : $current['sampleSize'],
                'comparison' => $availability === 'available' && $prior['value'] !== null ? [
                    'priorValue' => $prior['value'],
                    'change' => round($current['value'] - $prior['value'], 2),
                ] : null,
            ];
        })->values()->all();

        return [
            'registryVersion' => MetricRegistry::VERSION,
            'fictional' => true,
            'freshAt' => now()->toAtomString(),
            'timezone' => $context['timezone'],
            'currency' => $context['currency'],
            'period' => [
                'start' => $start->setTimezone($context['timezone'])->toAtomString(),
                'endExclusive' => $end->setTimezone($context['timezone'])->toAtomString(),
            ],
            'filters' => collect($filters)->except('_program_ids')->all(),
            'minimumCohort' => config('reporting.minimum_cohort'),
            'metrics' => $metrics,
            'options' => $this->options($user, $organisation, $role),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(array $filters, string $timezone): array
    {
        if (filled($filters['period_start'] ?? null) && filled($filters['period_end'] ?? null)) {
            return [
                CarbonImmutable::parse($filters['period_start'], $timezone)->startOfDay()->utc(),
                CarbonImmutable::parse($filters['period_end'], $timezone)->startOfDay()->utc(),
            ];
        }

        $now = CarbonImmutable::instance(now())->setTimezone($timezone);

        return [$now->startOfMonth()->utc(), $now->addDay()->startOfDay()->utc()];
    }

    /** @param array<string, mixed> $filters
     * @return array{value: float|null, sampleSize: int}
     */
    private function calculate(string $metric, CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        return match ($metric) {
            'service.requests_received' => $this->requestsReceived($start, $end, $filters),
            'service.case_interactions' => $this->caseInteractions($start, $end, $filters),
            'service.services_delivered' => $this->caseMetric(CaseMetricCode::ServiceDelivered, $start, $end, $filters),
            'service.goal_achievement_rate' => $this->goalRate($start, $end, $filters),
            'fundraising.successful_donations' => $this->successfulDonations($start, $end, $filters, false),
            'fundraising.net_raised' => $this->successfulDonations($start, $end, $filters, true),
            'engagement.welcome_deliveries' => $this->journeyMetric(SupporterJourneyEventType::Delivered, $start, $end, $filters, false),
            'engagement.meaningful_action_rate' => $this->journeyMetric(SupporterJourneyEventType::MeaningfulAction, $start, $end, $filters, true),
            default => ['value' => null, 'sampleSize' => 0],
        };
    }

    /** @param array<string, mixed> $filters
     * @return array{value: float, sampleSize: int}
     */
    private function requestsReceived(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $records = IntakeRequest::query()->with(['party.addresses', 'party.businessRoles'])
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->when(array_key_exists('_program_ids', $filters), fn (Builder $query) => $query->whereIn('program_id', $filters['_program_ids']))
            ->when(filled($filters['program_id'] ?? null), fn (Builder $query) => $query->where('program_id', $filters['program_id']))
            ->get()->filter(fn (IntakeRequest $intake): bool => $this->matchesParty($intake->party, $filters));

        return ['value' => (float) $records->count(), 'sampleSize' => $records->pluck('party_id')->unique()->count()];
    }

    /** @param array<string, mixed> $filters
     * @return array{value: float, sampleSize: int}
     */
    private function caseInteractions(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $records = CaseInteraction::query()->with(['serviceCase.party.addresses', 'serviceCase.party.businessRoles'])
            ->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->when(array_key_exists('_program_ids', $filters), fn (Builder $query) => $query->whereHas('serviceCase', fn (Builder $case) => $case->whereIn('program_id', $filters['_program_ids'])))
            ->when(filled($filters['program_id'] ?? null), fn (Builder $query) => $query->whereHas('serviceCase', fn (Builder $case) => $case->where('program_id', $filters['program_id'])))
            ->get()->filter(fn (CaseInteraction $interaction): bool => $this->matchesParty($interaction->serviceCase->party, $filters));

        return ['value' => (float) $records->count(), 'sampleSize' => $records->pluck('serviceCase.party_id')->unique()->count()];
    }

    /** @param array<string, mixed> $filters
     * @return array{value: float, sampleSize: int}
     */
    private function caseMetric(CaseMetricCode $code, CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $events = MetricEvent::query()->where('code', $code)->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->when(array_key_exists('_program_ids', $filters), fn (Builder $query) => $query->whereIn('program_id', $filters['_program_ids']))
            ->when(filled($filters['program_id'] ?? null), fn (Builder $query) => $query->where('program_id', $filters['program_id']))->get();
        $events = $this->filterMetricEventsByParty($events, $filters);

        return ['value' => (float) $events->sum(fn (MetricEvent $event): float => (float) $event->value), 'sampleSize' => $events->pluck('dimensions.party_id')->filter()->unique()->count()];
    }

    /** @param array<string, mixed> $filters
     * @return array{value: float|null, sampleSize: int}
     */
    private function goalRate(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $events = MetricEvent::query()->whereIn('code', [CaseMetricCode::GoalAchieved, CaseMetricCode::GoalNotAchieved])
            ->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->when(array_key_exists('_program_ids', $filters), fn (Builder $query) => $query->whereIn('program_id', $filters['_program_ids']))
            ->when(filled($filters['program_id'] ?? null), fn (Builder $query) => $query->where('program_id', $filters['program_id']))->get();
        $events = $this->filterMetricEventsByParty($events, $filters);
        $achieved = (float) $events->where('code', CaseMetricCode::GoalAchieved)->sum(fn (MetricEvent $event): float => (float) $event->value);
        $denominator = (float) $events->sum(fn (MetricEvent $event): float => (float) $event->value);

        return ['value' => $denominator > 0 ? round($achieved / $denominator * 100, 2) : null, 'sampleSize' => $events->pluck('dimensions.party_id')->filter()->unique()->count()];
    }

    /** @param array<string, mixed> $filters
     * @return array{value: float, sampleSize: int}
     */
    private function successfulDonations(CarbonImmutable $start, CarbonImmutable $end, array $filters, bool $money): array
    {
        $events = DonationPaymentEvent::query()->with(['payment.donation.party.addresses', 'payment.donation.party.businessRoles'])
            ->where('to_status', DonationPaymentStatus::Succeeded)->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)->get()
            ->filter(fn (DonationPaymentEvent $event): bool => $this->matchesDonation($event->payment->donation, $filters))->unique('donation_payment_id');
        $partyIds = $events->pluck('payment.donation.party_id')->unique();

        if (! $money) {
            return ['value' => (float) $events->count(), 'sampleSize' => $partyIds->count()];
        }

        $gross = $events->sum(fn (DonationPaymentEvent $event): int => $event->payment->amount_minor);
        $refunds = DonationRefund::query()->with(['payment.donation.party.addresses', 'payment.donation.party.businessRoles'])
            ->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)->get()
            ->filter(fn (DonationRefund $refund): bool => $this->matchesDonation($refund->payment->donation, $filters))
            ->sum('amount_minor');

        return ['value' => round(($gross - $refunds) / 100, 2), 'sampleSize' => $partyIds->count()];
    }

    /** @param array<string, mixed> $filters
     * @return array{value: float|null, sampleSize: int}
     */
    private function journeyMetric(SupporterJourneyEventType $type, CarbonImmutable $start, CarbonImmutable $end, array $filters, bool $rate): array
    {
        $events = $this->journeyEvents($start, $end, $filters);
        $matching = $events->where('type', $type)->unique('supporter_journey_recipient_id');

        if (! $rate) {
            return ['value' => (float) $matching->count(), 'sampleSize' => $matching->pluck('recipient.party_id')->unique()->count()];
        }

        $delivered = $events->where('type', SupporterJourneyEventType::Delivered)->unique('supporter_journey_recipient_id');

        return [
            'value' => $delivered->isNotEmpty() ? round($matching->count() / $delivered->count() * 100, 2) : null,
            'sampleSize' => $delivered->pluck('recipient.party_id')->unique()->count(),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, SupporterJourneyEvent>
     */
    private function journeyEvents(CarbonImmutable $start, CarbonImmutable $end, array $filters): Collection
    {
        return SupporterJourneyEvent::query()->with(['recipient.party.addresses', 'recipient.party.businessRoles', 'recipient.party.donations'])
            ->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)->get()
            ->filter(fn (SupporterJourneyEvent $event): bool => $this->matchesParty($event->recipient->party, $filters)
                && (! filled($filters['campaign_id'] ?? null) || $event->recipient->party->donations->contains('fundraising_campaign_id', $filters['campaign_id'])))->values();
    }

    /** @param array<string, mixed> $filters */
    private function matchesDonation(Donation $donation, array $filters): bool
    {
        return (! filled($filters['campaign_id'] ?? null) || $donation->fundraising_campaign_id === (int) $filters['campaign_id'])
            && $this->matchesParty($donation->party, $filters);
    }

    /** @param array<string, mixed> $filters */
    private function matchesParty(Party $party, array $filters): bool
    {
        return (! filled($filters['area'] ?? null) || $party->addresses->contains('service_area', $filters['area']))
            && (! filled($filters['location'] ?? null) || $party->addresses->contains('country_code', $filters['location']))
            && (! filled($filters['cohort'] ?? null) || $party->businessRoles->contains('role', PartyBusinessRole::from($filters['cohort'])));
    }

    /** @param Collection<int, MetricEvent> $events
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MetricEvent>
     */
    private function filterMetricEventsByParty(Collection $events, array $filters): Collection
    {
        if (! filled($filters['area'] ?? null) && ! filled($filters['location'] ?? null) && ! filled($filters['cohort'] ?? null)) {
            return $events;
        }

        $partyIds = Party::query()->with(['addresses', 'businessRoles'])->get()
            ->filter(fn (Party $party): bool => $this->matchesParty($party, $filters))->pluck('id');

        return $events->filter(fn (MetricEvent $event): bool => in_array($event->dimensions['party_id'] ?? null, $partyIds->all(), true))->values();
    }

    /** @return array{timezone: string, currency: string} */
    private function reportingContext(Organisation $organisation): array
    {
        $scenario = collect(ScenarioCatalog::organisations())->firstWhere('slug', $organisation->slug);

        return [
            'timezone' => $scenario['timezone'] ?? config('app.timezone'),
            'currency' => Donation::query()->value('currency') ?? ($scenario['currency'] ?? config('reporting.default_currency')),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return list<string>
     */
    private function activeDimensions(array $filters): array
    {
        return array_values(array_filter([
            'program_id' => 'program',
            'area' => 'area',
            'location' => 'location',
            'cohort' => 'cohort',
            'campaign_id' => 'campaign',
        ], fn (string $dimension, string $key): bool => filled($filters[$key] ?? null), ARRAY_FILTER_USE_BOTH));
    }

    /** @return array<string, mixed> */
    private function options(User $user, Organisation $organisation, ?OrganisationRole $role): array
    {
        $programs = Program::query()->orderBy('name')->get()->filter(fn (Program $program): bool => $role === OrganisationRole::ExecutiveViewer || $user->hasProgramAccess($program));

        return [
            'programs' => $programs->map(fn (Program $program): array => ['id' => $program->id, 'name' => $program->name])->values(),
            'areas' => PartyAddress::query()->whereNotNull('service_area')->distinct()->orderBy('service_area')->pluck('service_area'),
            'locations' => PartyAddress::query()->distinct()->orderBy('country_code')->pluck('country_code'),
            'cohorts' => collect(PartyBusinessRole::cases())->map(fn (PartyBusinessRole $cohort): array => ['value' => $cohort->value, 'label' => $cohort->label()]),
            'campaigns' => FundraisingCampaign::query()->orderBy('name')->get()->map(fn (FundraisingCampaign $campaign): array => ['id' => $campaign->id, 'name' => $campaign->name]),
        ];
    }
}
