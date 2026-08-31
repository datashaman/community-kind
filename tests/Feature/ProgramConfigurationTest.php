<?php

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\User;
use App\OrganisationContext;
use Inertia\Testing\AssertableInertia as Assert;

it('lets an administrator change validated Program terminology and measurement configuration without code', function () {
    $administrator = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $organisation->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $program = app(OrganisationContext::class)->run($organisation, fn (): Program => Program::factory()->for($organisation)->create());
    $configuration = [
        'labels' => ['request' => 'Support request', 'case' => 'Support journey'],
        'stages' => [['key' => 'received', 'label' => 'Received'], ['key' => 'active', 'label' => 'Active']],
        'outcome_measures' => [['key' => 'housing_stability', 'label' => 'Housing stability', 'unit' => 'score']],
        'taxonomies' => [['key' => 'need', 'label' => 'Presenting need', 'values' => ['Housing', 'Food']]],
    ];

    $this->actingAs($administrator)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'configuration' => $configuration,
        ])
        ->assertOk()
        ->assertJsonPath('configuration.labels.case', 'Support journey');

    app(OrganisationContext::class)->run($organisation, fn () => expect($program->refresh()->configuration)->toBe($configuration));

    $this->actingAs($administrator)
        ->get(route('programs.index', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('programs/index')
            ->where('programs.0.configuration.labels.case', 'Support journey')
            ->where('programs.0.canUpdate', true));
});

it('rejects malformed Program configuration', function () {
    $administrator = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $organisation->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $program = app(OrganisationContext::class)->run($organisation, fn (): Program => Program::factory()->for($organisation)->create());

    $this->actingAs($administrator)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'configuration' => [
                'labels' => ['request' => 'Request', 'case' => 'Case'],
                'stages' => [['key' => 'Not valid', 'label' => 'Invalid']],
                'outcome_measures' => [],
                'taxonomies' => [],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('configuration.stages.0.key');
});
