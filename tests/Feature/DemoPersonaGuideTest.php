<?php

use App\Actions\Demo\ProvisionSandboxPair;
use App\Enums\OrganisationRole;
use App\Models\Membership;
use App\Models\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['demo_sandbox.enabled' => true]);
    $result = app(ProvisionSandboxPair::class)->handle();
    $this->pair = $result['pair'];
    $this->post(route('demo.bootstrap.store', ['token' => $result['token']]))->assertRedirect();
    $this->organisation = $this->pair->organisations()->where('sandbox_template', 'harbourkind')->firstOrFail();
});

it('explains each perspective and links to meaningful permitted work', function () {
    $this->get(route('demo.personas.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('demo/personas')
            ->has('personas', 5)
            ->where('personas.0.roleKey', OrganisationRole::OrganisationAdministrator->value)
            ->where('personas.0.responsibility', fn (string $value): bool => str_contains($value, 'organisation'))
            ->where('personas.0.boundary', fn (string $value): bool => str_contains($value, 'Demo confinement'))
            ->has('personas.0.tasks', 3)
            ->where('personas.4.roleKey', OrganisationRole::ExecutiveViewer->value)
            ->has('personas.4.tasks', 2));

    $destinations = [
        OrganisationRole::OrganisationAdministrator->value => [
            'programs.index',
            'organisation-configurations.index',
            'audit.index',
        ],
        OrganisationRole::ProgramManager->value => [
            'intakes.index',
            'parties.index',
            'audit.index',
        ],
        OrganisationRole::CaseWorker->value => [
            'intakes.index',
            'parties.index',
            'audit.index',
        ],
        OrganisationRole::EngagementOfficer->value => [
            'donations.index',
            'volunteers.index',
            'supporter-journeys.index',
        ],
        OrganisationRole::ExecutiveViewer->value => [
            'dashboard',
            'impact-snapshots.index',
        ],
    ];

    foreach ($destinations as $role => $routeNames) {
        $membership = personaMembership($this->organisation, OrganisationRole::from($role));

        $this->post(route('demo.personas.store'), ['membership_id' => $membership->id])->assertRedirect();

        foreach ($routeNames as $routeName) {
            $this->get(route($routeName, ['current_organisation' => $this->organisation]))->assertOk();
        }
    }
});

it('shares the active perspective, supports switching, and preserves inaccessible boundaries', function () {
    $caseWorker = personaMembership($this->organisation, OrganisationRole::CaseWorker);
    $executive = personaMembership($this->organisation, OrganisationRole::ExecutiveViewer);

    $this->post(route('demo.personas.store'), ['membership_id' => $caseWorker->id])->assertRedirect();
    $this->get(route('dashboard', ['current_organisation' => $this->organisation]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('demoSandbox.persona.role', 'Case worker')
            ->where('demoSandbox.persona.organisation', $this->organisation->name)
            ->has('demoSandbox.persona.tasks', 3));

    $this->post(route('demo.personas.store'), ['membership_id' => $executive->id])->assertRedirect();
    $this->assertAuthenticatedAs($executive->user);
    $this->get(route('impact-snapshots.index', ['current_organisation' => $this->organisation]))->assertOk();
    $this->get(route('parties.index', ['current_organisation' => $this->organisation]))->assertForbidden();
});

function personaMembership(Organisation $organisation, OrganisationRole $role): Membership
{
    return Membership::query()->findOrFail((int) DB::table('role_assignments')
        ->where('organisation_id', $organisation->id)
        ->whereNull('program_id')
        ->whereNull('ended_at')
        ->where('role', $role->value)
        ->value('membership_id'));
}
