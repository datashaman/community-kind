<?php

use App\Data\Values\ClassifiedValue;
use App\Enums\OrganisationRole;
use App\Models\IntakeRequest;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\Program;
use App\Models\ProgramEligibilityQuestion;
use App\Models\ProgramIntakeField;
use App\Models\ProgramRiskFlag;
use App\Models\User;
use App\OrganisationContext;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{administrator: User, manager: User, managerMembership: Membership, organisation: Organisation, program: Program, party: Party, field: ProgramIntakeField, question: ProgramEligibilityQuestion, flag: ProgramRiskFlag} */
function programIntakeDefinitionFixture(): array
{
    $administrator = User::factory()->create();
    $manager = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $organisation->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $managerMembership = $organisation->memberships()->create(['user_id' => $manager->id, 'role' => OrganisationRole::ProgramManager]);

    return app(OrganisationContext::class)->run($organisation, function () use ($administrator, $manager, $managerMembership, $organisation): array {
        $program = Program::factory()->for($organisation)->create();
        $managerMembership->programs()->attach($program);
        $party = Party::factory()->for($organisation)->create();
        $party->programs()->attach($program);
        $field = $program->intakeFields()->create([
            'key' => 'current_situation',
            'label' => 'Current situation',
            'field_type' => 'textarea',
            'is_required' => true,
            'position' => 0,
        ]);
        $question = $program->eligibilityQuestions()->create([
            'key' => 'service_area',
            'label' => 'Lives in the service area',
            'is_required' => true,
            'position' => 0,
        ]);
        $flag = $program->riskFlags()->create([
            'key' => 'housing_loss',
            'label' => 'At risk of losing housing',
            'position' => 0,
        ]);

        return compact('administrator', 'manager', 'managerMembership', 'organisation', 'program', 'party', 'field', 'question', 'flag');
    });
}

it('manages ordered intake eligibility and risk definitions without JSON', function () {
    extract(programIntakeDefinitionFixture());

    $this->actingAs($administrator)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'intake_fields' => [
                ['id' => null, 'key' => null, 'label' => 'Preferred contact date', 'field_type' => 'date', 'is_required' => false, 'retired' => false],
                ['id' => $field->id, 'key' => $field->key, 'label' => 'Current circumstances', 'field_type' => 'textarea', 'is_required' => true, 'retired' => true],
            ],
            'eligibility_questions' => [
                ['id' => $question->id, 'key' => $question->key, 'label' => 'Lives within the service area', 'is_required' => true, 'retired' => false],
                ['id' => null, 'key' => null, 'label' => 'Program is a good fit', 'is_required' => false, 'retired' => false],
            ],
            'risk_flags' => [
                ['id' => $flag->id, 'key' => $flag->key, 'label' => 'Housing loss is imminent', 'retired' => true],
                ['id' => null, 'key' => null, 'label' => 'Immediate safety concern', 'retired' => false],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('intake_fields.0.key', 'preferred_contact_date')
        ->assertJsonPath('intake_fields.1.key', 'current_situation')
        ->assertJsonPath('intake_fields.1.retired', true)
        ->assertJsonPath('eligibility_questions.1.key', 'program_is_a_good_fit')
        ->assertJsonPath('risk_flags.1.key', 'immediate_safety_concern');

    app(OrganisationContext::class)->run($organisation, function () use ($program): void {
        expect($program->intakeFields()->pluck('key')->all())->toBe(['preferred_contact_date', 'current_situation'])
            ->and($program->eligibilityQuestions()->pluck('key')->all())->toBe(['service_area', 'program_is_a_good_fit'])
            ->and($program->riskFlags()->pluck('key')->all())->toBe(['housing_loss', 'immediate_safety_concern']);
    });
});

it('keeps retired definitions available to interpret an existing intake', function () {
    extract(programIntakeDefinitionFixture());

    $intake = app(OrganisationContext::class)->run($organisation, function () use ($field, $flag, $party, $program, $question): IntakeRequest {
        $intake = IntakeRequest::factory()->for($program)->for($party)->create([
            'encrypted_content' => new ClassifiedValue(json_encode([
                'narrative' => 'Historical narrative',
                'presenting_needs' => 'Historical needs',
                'intake_fields' => [$field->key => 'Staying with friends'],
            ], JSON_THROW_ON_ERROR)),
            'eligibility_context' => [$question->key => true],
            'risk_flags' => [$flag->key],
        ]);
        $field->update(['label' => 'Current circumstances', 'retired_at' => now()]);
        $question->update(['label' => 'Within service boundary', 'retired_at' => now()]);
        $flag->update(['label' => 'Housing loss is imminent', 'retired_at' => now()]);

        return $intake;
    });

    $this->actingAs($manager)
        ->get(route('intakes.show', [$organisation, $intake]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('intakes/show')
            ->where('intake.intakeFields.current_situation', 'Staying with friends')
            ->where('intake.intakeFieldDefinitions.0.label', 'Current circumstances')
            ->where('intake.intakeFieldDefinitions.0.retired', true)
            ->where('intake.eligibilityQuestions.0.label', 'Within service boundary')
            ->where('intake.eligibilityQuestions.0.retired', true)
            ->where('intake.riskFlagDefinitions.0.label', 'Housing loss is imminent')
            ->where('intake.riskFlagDefinitions.0.retired', true));
});

it('requires existing definitions to be retired and keeps their stable identities immutable', function () {
    extract(programIntakeDefinitionFixture());

    $this->actingAs($administrator)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $program->slug,
            'intake_fields' => [],
            'eligibility_questions' => [],
            'risk_flags' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['intake_fields', 'eligibility_questions', 'risk_flags']);

    app(OrganisationContext::class)->run($organisation, function () use ($field, $flag, $question): void {
        expect(fn () => $field->update(['key' => 'changed']))->toThrow(LogicException::class, 'stable identity')
            ->and(fn () => $question->update(['key' => 'changed']))->toThrow(LogicException::class, 'stable identity')
            ->and(fn () => $flag->update(['key' => 'changed']))->toThrow(LogicException::class, 'stable identity');
    });
});
