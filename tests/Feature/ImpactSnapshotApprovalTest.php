<?php

use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\User;
use App\OrganisationContext;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{organisation: Organisation, executive: User} */
function impactSnapshotFixture(): array
{
    $organisation = Organisation::factory()->active()->create();
    $executive = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $executive->id, 'role' => OrganisationRole::ExecutiveViewer]);

    return compact('organisation', 'executive');
}

/*
 * Approving a snapshot needs an active impact reporting configuration to say
 * which metrics may leave the dashboard. Without one the page used to offer the
 * action, take the form, and fail in the action with a LogicException that
 * reached the person as a 500.
 */
it('tells the page that a snapshot cannot be approved without an active configuration', function () {
    extract(impactSnapshotFixture());

    $this->actingAs($executive)
        ->get(route('impact-snapshots.index', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('impact-snapshots/index')
            ->where('canApprove', false));

    app(OrganisationContext::class)->run($organisation, fn (): OrganisationConfiguration => OrganisationConfiguration::factory()->create([
        'area' => OrganisationConfigurationArea::Reporting,
        'configuration_key' => 'impact',
        'status' => OrganisationConfigurationStatus::Active,
        'definition' => ['pack_metric_ids' => ['service.requests_received'], 'public_metric_ids' => []],
    ]));

    $this->actingAs($executive)
        ->get(route('impact-snapshots.index', $organisation))
        ->assertInertia(fn (Assert $page) => $page->where('canApprove', true));
});

/*
 * Approving a snapshot needs ExecutiveViewer; activating the configuration it
 * depends on needs OrganisationAdministrator. The person blocked is usually not
 * the person who can unblock, so the page must not send them to a 403.
 */
it('offers the configuration link only to a reader the reporting page will admit', function () {
    extract(impactSnapshotFixture());

    $this->actingAs($executive)
        ->get(route('impact-snapshots.index', $organisation))
        ->assertInertia(fn (Assert $page) => $page->where('canConfigureReporting', false));
    $this->actingAs($executive)
        ->get(route('reporting-publication.index', $organisation))
        ->assertForbidden();

    /* One membership per user, so the second role is another assignment on it. */
    $administrator = User::factory()->create();
    $membership = $organisation->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    app(OrganisationContext::class)->run($organisation, fn () => $membership->roleAssignments()->create(['role' => OrganisationRole::ExecutiveViewer]));

    $this->actingAs($administrator)
        ->get(route('impact-snapshots.index', $organisation))
        ->assertInertia(fn (Assert $page) => $page->where('canConfigureReporting', true));
});

it('reports a missing configuration on the form rather than as a server error', function () {
    extract(impactSnapshotFixture());

    $this->actingAs($executive)
        ->post(route('impact-snapshots.store', $organisation), [
            'audience' => 'board',
            'period_start' => '2026-08-01',
            'period_end' => '2026-09-01',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('audience');
});
