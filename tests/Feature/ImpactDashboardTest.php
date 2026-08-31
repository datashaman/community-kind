<?php

use App\Actions\Reporting\BuildImpactDashboard;
use App\Enums\CaseMetricCode;
use App\Enums\DonationPaymentStatus;
use App\Enums\OrganisationRole;
use App\Enums\PartyBusinessRole;
use App\Enums\SupporterJourneyEventType;
use App\Models\Donation;
use App\Models\DonationPayment;
use App\Models\DonationPaymentEvent;
use App\Models\DonationRefund;
use App\Models\IntakeRequest;
use App\Models\MetricEvent;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\Program;
use App\Models\SupporterJourneyEvent;
use App\Models\SupporterJourneyRecipient;
use App\Models\User;
use App\OrganisationContext;
use Database\Seeders\CommunityKindScenarioSeeder;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $key = 'base64:'.base64_encode(str_repeat('m', 32));
    config([
        'classified_data.encryption.current_version' => 'metrics-data-v1',
        'classified_data.encryption.keys' => ['metrics-data-v1' => $key],
        'classified_data.contact_index.current_version' => 'metrics-index-v1',
        'classified_data.contact_index.previous_version' => null,
        'classified_data.contact_index.keys' => ['metrics-index-v1' => $key],
        'reporting.minimum_cohort' => 5,
    ]);
    Date::setTestNow('2026-07-01 12:00:00 UTC');
});

afterEach(fn () => Date::setTestNow());

it('reconciles fixed definitions, half-open periods, comparisons, rates, money, and unavailable values', function () {
    [$organisation, $program, $manager, $engagement, $executive] = reportingFixture();

    app(OrganisationContext::class)->run($organisation, function () use ($organisation, $program): void {
        $party = Party::factory()->for($organisation)->create();
        $party->businessRoles()->create(['organisation_id' => $organisation->id, 'role' => PartyBusinessRole::Donor]);
        PartyAddress::factory()->for($party)->create(['service_area' => 'Harbour Ward', 'country_code' => 'ZA']);
        IntakeRequest::factory()->create(['party_id' => $party->id, 'program_id' => $program->id, 'created_at' => '2026-05-15 10:00:00']);
        IntakeRequest::factory()->create(['party_id' => $party->id, 'program_id' => $program->id, 'created_at' => '2026-06-01 00:00:00']);
        IntakeRequest::factory()->create(['party_id' => $party->id, 'program_id' => $program->id, 'created_at' => '2026-07-01 00:00:00']);
        MetricEvent::factory()->create(['program_id' => $program->id, 'code' => CaseMetricCode::ServiceDelivered, 'value' => 2, 'occurred_at' => '2026-06-10 12:00:00']);
        MetricEvent::factory()->create(['program_id' => $program->id, 'code' => CaseMetricCode::GoalAchieved, 'occurred_at' => '2026-06-20 12:00:00']);
        MetricEvent::factory()->create(['program_id' => $program->id, 'code' => CaseMetricCode::GoalNotAchieved, 'occurred_at' => '2026-06-21 12:00:00']);

        $donation = Donation::factory()->for($party)->create(['amount_minor' => 5000]);
        $payment = DonationPayment::factory()->for($donation)->create(['amount_minor' => 5000]);
        DonationPaymentEvent::factory()->for($payment, 'payment')->create(['to_status' => DonationPaymentStatus::Succeeded, 'occurred_at' => '2026-06-12 12:00:00']);
        DonationRefund::factory()->for($payment, 'payment')->create(['amount_minor' => 1000, 'occurred_at' => '2026-06-15 12:00:00']);
        DonationRefund::factory()->for($payment, 'payment')->create(['amount_minor' => 1000, 'occurred_at' => '2026-07-05 12:00:00']);
        $recipient = SupporterJourneyRecipient::factory()->for($party)->create();
        SupporterJourneyEvent::factory()->for($recipient, 'recipient')->create(['type' => SupporterJourneyEventType::Delivered, 'to_status' => 'delivered', 'occurred_at' => '2026-06-16 12:00:00']);
        SupporterJourneyEvent::factory()->for($recipient, 'recipient')->create(['type' => SupporterJourneyEventType::MeaningfulAction, 'from_status' => 'delivered', 'to_status' => 'delivered', 'occurred_at' => '2026-06-17 12:00:00']);
    });

    $filters = ['period_start' => '2026-06-01', 'period_end' => '2026-07-01', 'program_id' => $program->id];
    $managerDashboard = app(OrganisationContext::class)->run($organisation, fn () => app(BuildImpactDashboard::class)->handle($manager, $organisation, $filters));
    $managerMetrics = collect($managerDashboard['metrics'])->keyBy('definition.id');
    expect($managerMetrics)->toHaveCount(4)
        ->and($managerMetrics['service.requests_received']['value'])->toBe(1.0)
        ->and($managerMetrics['service.requests_received']['comparison']['priorValue'])->toBe(1.0)
        ->and($managerMetrics['service.services_delivered']['value'])->toBe(2.0)
        ->and($managerMetrics['service.goal_achievement_rate']['value'])->toBe(50.0)
        ->and($managerDashboard['period']['endExclusive'])->toStartWith('2026-07-01T00:00:00');

    $engagementFilters = ['period_start' => '2026-06-01', 'period_end' => '2026-07-01'];
    $engagementDashboard = app(OrganisationContext::class)->run($organisation, fn () => app(BuildImpactDashboard::class)->handle($engagement, $organisation, $engagementFilters));
    $engagementMetrics = collect($engagementDashboard['metrics'])->keyBy('definition.id');
    expect($engagementMetrics)->toHaveCount(6)
        ->and($engagementMetrics['fundraising.successful_donations']['value'])->toBe(1.0)
        ->and($engagementMetrics['fundraising.net_raised']['value'])->toBe(40.0)
        ->and($engagementMetrics['engagement.welcome_deliveries']['value'])->toBe(1.0)
        ->and($engagementMetrics['engagement.meaningful_action_rate']['value'])->toBe(100.0);

    $refundPeriod = app(OrganisationContext::class)->run($organisation, fn () => app(BuildImpactDashboard::class)->handle($engagement, $organisation, ['period_start' => '2026-07-01', 'period_end' => '2026-08-01']));
    $refundMetrics = collect($refundPeriod['metrics'])->keyBy('definition.id');
    expect($refundMetrics['fundraising.successful_donations']['value'])->toBe(0.0)
        ->and($refundMetrics['fundraising.net_raised']['value'])->toBe(-10.0);

    $empty = app(OrganisationContext::class)->run($organisation, fn () => app(BuildImpactDashboard::class)->handle($executive, $organisation, ['period_start' => '2025-01-01', 'period_end' => '2025-02-01']));
    $emptyMetrics = collect($empty['metrics'])->keyBy('definition.id');
    expect($emptyMetrics)->toHaveCount(10)
        ->and($emptyMetrics['service.requests_received']['value'])->toBe(0.0)
        ->and($emptyMetrics['service.goal_achievement_rate']['availability'])->toBe('unavailable')
        ->and($emptyMetrics['engagement.meaningful_action_rate']['availability'])->toBe('unavailable');
});

it('suppresses small sensitive slices and does not expose forbidden program drill-downs', function () {
    [$organisation, $program, $manager] = reportingFixture();
    $hiddenProgram = app(OrganisationContext::class)->run($organisation, fn () => Program::factory()->for($organisation)->create());

    app(OrganisationContext::class)->run($organisation, function () use ($hiddenProgram, $organisation, $program): void {
        $party = Party::factory()->for($organisation)->create();
        PartyAddress::factory()->for($party)->create(['service_area' => 'Small Ward', 'country_code' => 'ZA']);
        IntakeRequest::factory()->create(['party_id' => $party->id, 'program_id' => $program->id, 'created_at' => '2026-06-10']);
        MetricEvent::factory()->create(['program_id' => $program->id, 'code' => CaseMetricCode::ServiceDelivered, 'value' => 2, 'dimensions' => ['party_id' => $party->id], 'occurred_at' => '2026-06-10']);
        $otherParty = Party::factory()->for($organisation)->create();
        PartyAddress::factory()->for($otherParty)->create(['service_area' => 'Other Ward', 'country_code' => 'ZA']);
        MetricEvent::factory()->create(['program_id' => $program->id, 'code' => CaseMetricCode::ServiceDelivered, 'value' => 50, 'dimensions' => ['party_id' => $otherParty->id], 'occurred_at' => '2026-06-10']);
        MetricEvent::factory()->create(['program_id' => $hiddenProgram->id, 'code' => CaseMetricCode::ServiceDelivered, 'value' => 99, 'occurred_at' => '2026-06-10']);
    });

    $slice = app(OrganisationContext::class)->run($organisation, fn () => app(BuildImpactDashboard::class)->handle($manager, $organisation, ['period_start' => '2026-06-01', 'period_end' => '2026-07-01', 'area' => 'Small Ward']));
    $sliceMetrics = collect($slice['metrics'])->keyBy('definition.id');
    expect($sliceMetrics['service.requests_received']['availability'])->toBe('suppressed')
        ->and($sliceMetrics['service.requests_received']['value'])->toBeNull()
        ->and($sliceMetrics['service.requests_received']['sampleSize'])->toBeNull();
    config(['reporting.minimum_cohort' => 1]);
    $visibleSlice = app(OrganisationContext::class)->run($organisation, fn () => app(BuildImpactDashboard::class)->handle($manager, $organisation, ['period_start' => '2026-06-01', 'period_end' => '2026-07-01', 'area' => 'Small Ward']));
    expect(collect($visibleSlice['metrics'])->keyBy('definition.id')['service.services_delivered']['value'])->toBe(2.0);

    $this->actingAs($manager)->get(route('dashboard', [$organisation, 'program_id' => $hiddenProgram->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('impact.metrics.2.value', 0)
            ->missing('impact.metrics.0.records')
            ->missing('impact.metrics.0.partyIds'));
    $this->actingAs($manager)->get(route('dashboard', [$organisation, 'period_start' => '2026-06-01', 'period_end' => '2026-07-01']))
        ->assertInertia(fn (Assert $page) => $page->where('impact.metrics.2.value', 52));
});

it('exactly reconciles the versioned seeded service, donation, and communication journeys', function () {
    $this->seed(CommunityKindScenarioSeeder::class);
    $organisation = Organisation::query()->where('slug', 'harbourkind')->firstOrFail();
    $manager = User::query()->where('email', 'manager@harbourkind.example.test')->firstOrFail();
    $engagement = User::query()->where('email', 'engagement@harbourkind.example.test')->firstOrFail();
    $period = ['period_start' => '2026-06-01', 'period_end' => '2026-07-01'];

    $managerMetrics = app(OrganisationContext::class)->run($organisation, fn () => collect(app(BuildImpactDashboard::class)->handle($manager, $organisation, $period)['metrics'])->keyBy('definition.id'));
    $engagementMetrics = app(OrganisationContext::class)->run($organisation, fn () => collect(app(BuildImpactDashboard::class)->handle($engagement, $organisation, $period)['metrics'])->keyBy('definition.id'));

    expect($managerMetrics['service.requests_received']['value'])->toBe(1.0)
        ->and($managerMetrics['service.services_delivered']['value'])->toBe(1.0)
        ->and($managerMetrics['service.goal_achievement_rate']['value'])->toBe(100.0)
        ->and($engagementMetrics['fundraising.successful_donations']['value'])->toBe(1.0)
        ->and($engagementMetrics['fundraising.net_raised']['value'])->toBe(50.0)
        ->and($engagementMetrics['engagement.welcome_deliveries']['value'])->toBe(1.0)
        ->and($engagementMetrics['engagement.meaningful_action_rate']['value'])->toBe(100.0)
        ->and($engagementMetrics['engagement.volunteer_applications']['value'])->toBe(100.0)
        ->and($engagementMetrics['engagement.volunteer_hours']['value'])->toBe(400.0);
});

/** @return array{Organisation, Program, User, User, User} */
function reportingFixture(): array
{
    $organisation = Organisation::factory()->active()->create(['slug' => 'harbourkind']);
    $manager = User::factory()->create();
    $engagement = User::factory()->create();
    $executive = User::factory()->create();
    $managerMembership = $organisation->memberships()->create(['user_id' => $manager->id, 'role' => OrganisationRole::ProgramManager]);
    $organisation->memberships()->create(['user_id' => $engagement->id, 'role' => OrganisationRole::EngagementOfficer]);
    $organisation->memberships()->create(['user_id' => $executive->id, 'role' => OrganisationRole::ExecutiveViewer]);
    $program = app(OrganisationContext::class)->run($organisation, fn () => Program::factory()->for($organisation)->create());
    app(OrganisationContext::class)->run($organisation, fn () => $managerMembership->programs()->attach($program));

    return [$organisation, $program, $manager, $engagement, $executive];
}
