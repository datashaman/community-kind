<?php

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\ProgramStage;
use App\Models\User;
use App\OrganisationContext;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{administrator: User, organisation: Organisation, program: Program, received: ProgramStage, active: ProgramStage} */
function createProgramConfigurationFixture(): array
{
    $administrator = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $organisation->memberships()->create([
        'user_id' => $administrator->id,
        'role' => OrganisationRole::OrganisationAdministrator,
    ]);

    return app(OrganisationContext::class)->run($organisation, function () use ($administrator, $organisation): array {
        $program = Program::factory()->for($organisation)->create([
            'request_label' => 'Request',
            'case_label' => 'Case',
            'configuration' => [],
        ]);
        $received = $program->stages()->create(['key' => 'received', 'label' => 'Received', 'position' => 0]);
        $active = $program->stages()->create(['key' => 'active', 'label' => 'Active', 'position' => 1]);

        return compact('administrator', 'organisation', 'program', 'received', 'active');
    });
}

it('lets an administrator manage Program terminology and an ordered service pathway without JSON', function () {
    extract(createProgramConfigurationFixture());

    $this->actingAs($administrator)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'request_label' => 'Support request',
            'case_label' => 'Support journey',
            'stages' => [
                ['id' => $active->id, 'key' => $active->key, 'label' => 'Working together', 'retired' => false],
                ['id' => $received->id, 'key' => $received->key, 'label' => 'Received', 'retired' => true],
                ['id' => null, 'key' => null, 'label' => 'Review progress', 'retired' => false],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('request_label', 'Support request')
        ->assertJsonPath('case_label', 'Support journey')
        ->assertJsonPath('stages.0.key', 'active')
        ->assertJsonPath('stages.0.label', 'Working together')
        ->assertJsonPath('stages.1.retired', true)
        ->assertJsonPath('stages.2.key', 'review_progress');

    app(OrganisationContext::class)->run($organisation, function () use ($program, $received): void {
        $program->refresh();

        expect($program->request_label)->toBe('Support request')
            ->and($program->case_label)->toBe('Support journey')
            ->and($program->configuration)->toBe([])
            ->and($program->stages()->pluck('key')->all())->toBe(['active', 'received', 'review_progress'])
            ->and($received->refresh()->retired_at)->not->toBeNull();
    });

    $this->actingAs($administrator)
        ->get(route('programs.index', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('programs/index')
            ->where('programs.0.request_label', 'Support request')
            ->where('programs.0.case_label', 'Support journey')
            ->where('programs.0.stages.0.key', 'active')
            ->where('programs.0.canUpdate', true));
});

it('requires at least one active Program stage', function () {
    extract(createProgramConfigurationFixture());

    $this->actingAs($administrator)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'request_label' => 'Request',
            'case_label' => 'Case',
            'stages' => [
                ['id' => $received->id, 'key' => $received->key, 'label' => 'Received', 'retired' => true],
                ['id' => $active->id, 'key' => $active->key, 'label' => 'Active', 'retired' => true],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stages');
});

it('requires existing stages to be retired rather than silently omitted', function () {
    extract(createProgramConfigurationFixture());

    $this->actingAs($administrator)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'request_label' => 'Request',
            'case_label' => 'Case',
            'stages' => [
                ['id' => $active->id, 'key' => $active->key, 'label' => 'Active', 'retired' => false],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stages');
});

it('keeps a Program stage stable identity immutable while allowing retirement', function () {
    extract(createProgramConfigurationFixture());

    app(OrganisationContext::class)->run($organisation, function () use ($received): void {
        $received->update(['retired_at' => now()]);

        expect($received->refresh()->retired_at)->not->toBeNull()
            ->and(fn () => $received->update(['key' => 'renamed']))
            ->toThrow(LogicException::class, 'stable identity');
    });
});

it('returns an Inertia form submission to the Program pathway editor', function () {
    extract(createProgramConfigurationFixture());
    $editorUrl = route('programs.index', $organisation);

    $this->actingAs($administrator)
        ->from($editorUrl)
        ->withHeader('X-Inertia', 'true')
        ->patch(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'request_label' => 'Support request',
            'case_label' => 'Support journey',
            'stages' => [
                ['id' => $received->id, 'key' => $received->key, 'label' => 'Received', 'retired' => false],
                ['id' => $active->id, 'key' => $active->key, 'label' => 'Active', 'retired' => false],
            ],
        ])
        ->assertRedirect($editorUrl);
});

it('rejects a stage owned by another Organisation', function () {
    extract(createProgramConfigurationFixture());
    $otherOrganisation = Organisation::factory()->active()->create();
    $otherStage = app(OrganisationContext::class)->run($otherOrganisation, function () use ($otherOrganisation): ProgramStage {
        $otherProgram = Program::factory()->for($otherOrganisation)->create();

        return $otherProgram->stages()->create(['key' => 'secret', 'label' => 'Secret', 'position' => 0]);
    });

    $this->actingAs($administrator)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'request_label' => 'Request',
            'case_label' => 'Case',
            'stages' => [
                ['id' => $otherStage->id, 'key' => $otherStage->key, 'label' => 'Leaked', 'retired' => false],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stages.0.id');
});

it('rejects terminology and stages hidden inside Program configuration JSON', function () {
    extract(createProgramConfigurationFixture());

    $this->actingAs($administrator)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'request_label' => 'Request',
            'case_label' => 'Case',
            'stages' => [
                ['id' => $received->id, 'key' => $received->key, 'label' => 'Received', 'retired' => false],
                ['id' => $active->id, 'key' => $active->key, 'label' => 'Active', 'retired' => false],
            ],
            'configuration' => [
                'labels' => ['request' => 'Hidden request', 'case' => 'Hidden case'],
                'stages' => [['key' => 'hidden', 'label' => 'Hidden']],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('configuration');
});
