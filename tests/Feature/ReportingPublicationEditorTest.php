<?php

use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\User;
use App\OrganisationContext;
use App\Reporting\MetricRegistry;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{organisation: Organisation, administrator: User, officer: User} */
function reportingPublicationEditorFixture(): array
{
    $organisation = Organisation::factory()->active()->create();
    $administrator = User::factory()->create();
    $officer = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $organisation->memberships()->create(['user_id' => $officer->id, 'role' => OrganisationRole::EngagementOfficer]);

    return compact('organisation', 'administrator', 'officer');
}

it('creates previews and activates reporting selections from the metric catalogue', function () {
    extract(reportingPublicationEditorFixture());

    $this->actingAs($administrator)
        ->post(route('reporting-publication.store', $organisation), [
            'public_metric_ids' => ['engagement.event_attendance'],
            'pack_metric_ids' => ['service.requests_received', 'engagement.event_attendance'],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $version = app(OrganisationContext::class)->run($organisation, fn (): OrganisationConfiguration => OrganisationConfiguration::query()
        ->where('area', OrganisationConfigurationArea::Reporting)
        ->where('configuration_key', 'impact')
        ->sole());
    expect($version->definition)->toBe([
        'public_metric_ids' => ['engagement.event_attendance'],
        'pack_metric_ids' => ['service.requests_received', 'engagement.event_attendance'],
    ])->and($version->status)->toBe(OrganisationConfigurationStatus::Draft);

    $this->actingAs($administrator)
        ->get(route('reporting-publication.index', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reporting-publication/index')
            ->where('metrics', fn ($metrics) => collect($metrics)->contains('id', 'engagement.event_attendance'))
            ->where('versions.0.publicMetrics.0.label', 'Event attendance')
            ->where('versions.0.packMetrics.0.label', 'Requests received')
            ->where('versions.0.canActivate', true));

    $this->actingAs($administrator)
        ->post(route('reporting-publication.activate', [$organisation, $version]))
        ->assertRedirect();
    expect($version->refresh()->status)->toBe(OrganisationConfigurationStatus::Active);
});

it('shows retired metrics in immutable legacy versions without making them selectable', function () {
    extract(reportingPublicationEditorFixture());

    $legacy = app(OrganisationContext::class)->run($organisation, fn (): OrganisationConfiguration => OrganisationConfiguration::factory()->create([
        'area' => OrganisationConfigurationArea::Reporting,
        'configuration_key' => 'impact',
        'status' => OrganisationConfigurationStatus::Draft,
        'definition' => [
            'public_metric_ids' => ['legacy.people_reached', 'engagement.event_attendance'],
            'pack_metric_ids' => ['legacy.people_reached'],
        ],
    ]));

    $this->actingAs($administrator)
        ->get(route('reporting-publication.index', $organisation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics', fn ($metrics) => collect($metrics)->doesntContain('id', 'legacy.people_reached'))
            ->where('versions.0.publicMetrics.0', [
                'id' => 'legacy.people_reached',
                'label' => 'legacy.people_reached',
                'domain' => null,
                'unit' => null,
                'available' => false,
            ])
            ->where('versions.0.publicMetrics.1.available', true)
            ->where('versions.0.hasUnavailableMetrics', true)
            ->where('versions.0.canActivate', false));

    $this->actingAs($administrator)
        ->post(route('reporting-publication.activate', [$organisation, $legacy]))
        ->assertStatus(409);

    expect($legacy->refresh()->definition)->toBe([
        'public_metric_ids' => ['legacy.people_reached', 'engagement.event_attendance'],
        'pack_metric_ids' => ['legacy.people_reached'],
    ]);
});

it('rejects unavailable metrics and removes reporting from the generic JSON workflow', function () {
    extract(reportingPublicationEditorFixture());

    $this->actingAs($administrator)
        ->post(route('reporting-publication.store', $organisation), [
            'public_metric_ids' => ['legacy.people_reached'],
            'pack_metric_ids' => [],
        ])
        ->assertSessionHasErrors(['public_metric_ids.0', 'pack_metric_ids']);
    $this->actingAs($administrator)
        ->post("/{$organisation->slug}/organisation-configurations", [
            'area' => 'reporting',
            'configuration_key' => 'impact',
            'definition_json' => '{}',
        ])
        ->assertNotFound();
    $this->actingAs($officer)
        ->get(route('reporting-publication.index', $organisation))
        ->assertForbidden();
});

it('only permits activation of the latest reporting draft', function () {
    extract(reportingPublicationEditorFixture());
    $metricIds = app(MetricRegistry::class)->ids();

    foreach ([$metricIds[0], $metricIds[1]] as $metricId) {
        $this->actingAs($administrator)
            ->post(route('reporting-publication.store', $organisation), [
                'public_metric_ids' => [$metricId],
                'pack_metric_ids' => [$metricId],
            ])
            ->assertSessionHasNoErrors();
    }
    $drafts = app(OrganisationContext::class)->run($organisation, fn () => OrganisationConfiguration::query()
        ->where('area', OrganisationConfigurationArea::Reporting)
        ->where('configuration_key', 'impact')
        ->orderBy('version')
        ->get());

    $this->actingAs($administrator)
        ->post("/{$organisation->slug}/organisation-configurations/{$drafts->last()->id}/activate")
        ->assertNotFound();
    $this->actingAs($administrator)
        ->post(route('reporting-publication.activate', [$organisation, $drafts->first()]))
        ->assertStatus(409);
});
