<?php

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('removes generic configuration routes and obsolete Program JSON storage', function () {
    expect(Route::has('organisation-configurations.index'))->toBeFalse()
        ->and(Route::has('organisation-configurations.store'))->toBeFalse()
        ->and(Route::has('organisation-configurations.activate'))->toBeFalse()
        ->and(Schema::hasColumn('programs', 'configuration'))->toBeFalse()
        ->and(Schema::hasColumn('programs', 'case_default_classification'))->toBeTrue()
        ->and(Schema::hasColumn('organisation_configurations', 'definition'))->toBeTrue();
});

it('cannot fall back to user-authored JSON through a Program or Organisation endpoint', function () {
    $organisation = Organisation::factory()->active()->create();
    $administrator = User::factory()->create();
    $organisation->memberships()->create([
        'user_id' => $administrator->id,
        'role' => OrganisationRole::OrganisationAdministrator,
    ]);
    $program = app(OrganisationContext::class)->run(
        $organisation,
        fn (): Program => Program::factory()->for($organisation)->create(),
    );

    $this->actingAs($administrator)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'configuration' => ['case_default_classification' => 'highly_restricted'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('configuration');
    $this->actingAs($administrator)
        ->post("/{$organisation->slug}/organisation-configurations", [
            'area' => 'reporting',
            'configuration_key' => 'impact',
            'definition_json' => '{}',
        ])
        ->assertNotFound();
});
