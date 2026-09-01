<?php

use App\Enums\CaseMetricCode;
use App\Enums\OrganisationRole;
use App\Enums\TenantAuditEventType;
use App\Models\MetricEvent;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\Program;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\OrganisationContext;

beforeEach(function () {
    $key = 'base64:'.base64_encode(str_repeat('e', 32));
    config([
        'classified_data.encryption.current_version' => 'export-data-v1',
        'classified_data.encryption.keys' => ['export-data-v1' => $key],
        'classified_data.contact_index.current_version' => 'export-index-v1',
        'classified_data.contact_index.keys' => ['export-index-v1' => $key],
        'reporting.minimum_cohort' => 5,
    ]);
});

it('exports the exact privacy-safe dashboard context and audits it separately', function () {
    [$organisation, $manager, $program, $party] = impactExportFixture();
    app(OrganisationContext::class)->run($organisation, fn () => MetricEvent::factory()->create([
        'program_id' => $program->id,
        'code' => CaseMetricCode::ServiceDelivered,
        'value' => 2,
        'dimensions' => ['party_id' => $party->id],
        'occurred_at' => '2026-06-10 12:00:00',
    ]));

    $query = ['period_start' => '2026-06-01', 'period_end' => '2026-07-01'];
    $csv = $this->actingAs($manager)->get(route('dashboard.impact.export', [$organisation, ...$query]))
        ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
    expect($csv)->toContain('service.services_delivered')
        ->toContain(',2,available,count,')
        ->toContain('2026.4')
        ->toContain('Fictional')
        ->not->toContain($party->uuid)
        ->not->toContain('case note');

    $suppressed = $this->actingAs($manager)->get(route('dashboard.impact.export', [$organisation, ...$query, 'area' => 'Small Ward']))
        ->assertOk()->streamedContent();
    expect($suppressed)->toContain(',suppressed,')
        ->toContain('Small Ward')
        ->not->toContain($party->uuid);

    $chart = $this->actingAs($manager)->get(route('dashboard.impact.chart.export', [$organisation, ...$query, 'area' => 'Small Ward']))
        ->assertOk()
        ->assertHeader('content-type', 'image/svg+xml; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename="fictional-impact-chart.svg"')
        ->getContent();
    expect($chart)->toContain('role="img"')
        ->toContain('<title id="title">CommunityKind impact chart</title>')
        ->toContain('<desc id="description">')
        ->toContain('Fictional data')
        ->toContain('Small Ward')
        ->toContain('service.services_delivered v2026.4')
        ->toContain('Suppressed')
        ->not->toContain($party->uuid)
        ->not->toContain('case note');

    app(OrganisationContext::class)->run($organisation, function (): void {
        $audits = TenantAuditEvent::query()->where('type', TenantAuditEventType::ImpactReportExported)->get();
        expect($audits)->toHaveCount(3)
            ->and($audits->every(fn (TenantAuditEvent $audit): bool => $audit->payload['metric_count'] === 4 && $audit->payload['registry_version'] === '2026.4'))->toBeTrue()
            ->and($audits->pluck('payload.format')->sort()->values()->all())->toBe(['csv', 'csv', 'svg']);
    });
});

it('forbids exports for roles without impact definitions and across organisations', function () {
    [$organisation] = impactExportFixture();
    $worker = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $worker->id, 'role' => OrganisationRole::CaseWorker]);
    $other = Organisation::factory()->active()->create();

    $this->actingAs($worker)->get(route('dashboard.impact.export', $organisation))->assertForbidden();
    $this->actingAs($worker)->get(route('dashboard.impact.chart.export', $organisation))->assertForbidden();
    $this->actingAs($worker)->get(route('dashboard.impact.export', $other))->assertForbidden();
    $this->actingAs($worker)->get(route('dashboard.impact.chart.export', $other))->assertForbidden();
});

/** @return array{Organisation, User, Program, Party} */
function impactExportFixture(): array
{
    $organisation = Organisation::factory()->active()->create(['slug' => 'harbourkind']);
    $manager = User::factory()->create();
    $membership = $organisation->memberships()->create(['user_id' => $manager->id, 'role' => OrganisationRole::ProgramManager]);
    [$program, $party] = app(OrganisationContext::class)->run($organisation, function () use ($organisation): array {
        $program = Program::factory()->for($organisation)->create();
        $party = Party::factory()->for($organisation)->create();
        PartyAddress::factory()->for($party)->create(['service_area' => 'Small Ward', 'country_code' => 'ZA']);

        return [$program, $party];
    });
    app(OrganisationContext::class)->run($organisation, fn () => $membership->programs()->attach($program));

    return [$organisation, $manager, $program, $party];
}
