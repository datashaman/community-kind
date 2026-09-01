<?php

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\ProgramOutcomeMeasure;
use App\Models\ProgramTaxonomy;
use App\Models\ProgramTaxonomyValue;
use App\Models\User;
use App\OrganisationContext;

/** @return array{administrator: User, organisation: Organisation, program: Program, progress: ProgramOutcomeMeasure, wellbeing: ProgramOutcomeMeasure, taxonomy: ProgramTaxonomy, housing: ProgramTaxonomyValue, food: ProgramTaxonomyValue} */
function createProgramOutcomeTaxonomyFixture(): array
{
    $administrator = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $organisation->memberships()->create([
        'user_id' => $administrator->id,
        'role' => OrganisationRole::OrganisationAdministrator,
    ]);

    return app(OrganisationContext::class)->run($organisation, function () use ($administrator, $organisation): array {
        $program = Program::factory()->for($organisation)->create();
        $progress = $program->outcomeMeasures()->create(['key' => 'progress', 'label' => 'Progress', 'unit' => 'score', 'position' => 0]);
        $wellbeing = $program->outcomeMeasures()->create(['key' => 'wellbeing', 'label' => 'Wellbeing', 'unit' => 'score', 'position' => 1]);
        $taxonomy = $program->taxonomies()->create(['key' => 'need', 'label' => 'Presenting need', 'position' => 0]);
        $housing = $taxonomy->values()->create(['key' => 'housing', 'label' => 'Housing', 'position' => 0]);
        $food = $taxonomy->values()->create(['key' => 'food', 'label' => 'Food', 'position' => 1]);

        return compact('administrator', 'organisation', 'program', 'progress', 'wellbeing', 'taxonomy', 'housing', 'food');
    });
}

it('manages ordered outcome measures taxonomies and allowed values without JSON', function () {
    extract(createProgramOutcomeTaxonomyFixture());

    $this->actingAs($administrator)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'outcome_measures' => [
                ['id' => $wellbeing->id, 'key' => $wellbeing->key, 'label' => 'Personal wellbeing', 'unit' => 'percent', 'retired' => false],
                ['id' => $progress->id, 'key' => $progress->key, 'label' => 'Progress', 'unit' => 'score', 'retired' => true],
                ['id' => null, 'key' => null, 'label' => 'Housing stability', 'unit' => 'score', 'retired' => false],
            ],
            'taxonomies' => [
                [
                    'id' => $taxonomy->id,
                    'key' => $taxonomy->key,
                    'label' => 'Primary need',
                    'retired' => false,
                    'values' => [
                        ['id' => $food->id, 'key' => $food->key, 'label' => 'Food security', 'retired' => false],
                        ['id' => $housing->id, 'key' => $housing->key, 'label' => 'Housing', 'retired' => true],
                        ['id' => null, 'key' => null, 'label' => 'Employment', 'retired' => false],
                    ],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('outcome_measures.0.key', 'wellbeing')
        ->assertJsonPath('outcome_measures.0.label', 'Personal wellbeing')
        ->assertJsonPath('outcome_measures.1.retired', true)
        ->assertJsonPath('outcome_measures.2.key', 'housing_stability')
        ->assertJsonPath('taxonomies.0.key', 'need')
        ->assertJsonPath('taxonomies.0.values.0.key', 'food')
        ->assertJsonPath('taxonomies.0.values.2.key', 'employment');

    app(OrganisationContext::class)->run($organisation, function () use ($program, $progress, $housing): void {
        expect($program->outcomeMeasures()->pluck('key')->all())->toBe(['wellbeing', 'progress', 'housing_stability'])
            ->and($program->taxonomies()->firstOrFail()->values()->pluck('key')->all())->toBe(['food', 'housing', 'employment'])
            ->and($progress->refresh()->retired_at)->not->toBeNull()
            ->and($housing->refresh()->retired_at)->not->toBeNull();
    });
});

it('requires existing measures taxonomies and values to be retired rather than omitted', function () {
    extract(createProgramOutcomeTaxonomyFixture());

    $this->actingAs($administrator)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'outcome_measures' => [
                ['id' => $progress->id, 'key' => $progress->key, 'label' => 'Progress', 'unit' => 'score', 'retired' => false],
            ],
            'taxonomies' => [
                [
                    'id' => $taxonomy->id,
                    'key' => $taxonomy->key,
                    'label' => 'Need',
                    'retired' => false,
                    'values' => [
                        ['id' => $housing->id, 'key' => $housing->key, 'label' => 'Housing', 'retired' => false],
                    ],
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['outcome_measures', 'taxonomies.0.values']);
});

it('keeps outcome taxonomy and value stable identities immutable', function () {
    extract(createProgramOutcomeTaxonomyFixture());

    app(OrganisationContext::class)->run($organisation, function () use ($progress, $taxonomy, $housing): void {
        expect(fn () => $progress->update(['key' => 'changed']))->toThrow(LogicException::class, 'stable identity')
            ->and(fn () => $taxonomy->update(['key' => 'changed']))->toThrow(LogicException::class, 'stable identity')
            ->and(fn () => $housing->update(['key' => 'changed']))->toThrow(LogicException::class, 'stable identity');
    });
});
