<?php

use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\User;
use App\OrganisationContext;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{organisation: Organisation, administrator: User, officer: User} */
function intakeRulesEditorFixture(): array
{
    $organisation = Organisation::factory()->active()->create();
    $administrator = User::factory()->create();
    $officer = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $organisation->memberships()->create(['user_id' => $officer->id, 'role' => OrganisationRole::CaseWorker]);

    return compact('organisation', 'administrator', 'officer');
}

it('creates previews and activates typed intake rules with fixed safeguards', function () {
    extract(intakeRulesEditorFixture());

    $this->actingAs($administrator)
        ->post(route('intake-rules.store', $organisation), [
            'required_contact_fields' => ['email'],
            'default_urgency' => 'priority',
            'allow_restricted_access_bypass' => false,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $rule = app(OrganisationContext::class)->run($organisation, fn (): OrganisationConfiguration => OrganisationConfiguration::query()
        ->where('area', OrganisationConfigurationArea::IntakeRules)
        ->where('configuration_key', 'default')
        ->sole());
    expect($rule->definition)->toMatchArray([
        'required_fields' => ['party_uuid', 'program_id', 'source', 'narrative', 'presenting_needs', 'email'],
        'default_urgency' => 'priority',
        'allow_restricted_access_bypass' => false,
    ])->and($rule->status)->toBe(OrganisationConfigurationStatus::Draft);

    $this->actingAs($administrator)
        ->get(route('intake-rules.index', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('intake-rules/index')
            ->where('rules.0.defaultUrgency', 'priority')
            ->where('rules.0.requiredFields.5', 'email')
            ->where('rules.0.canActivate', true));
    $this->actingAs($administrator)
        ->post(route('intake-rules.activate', [$organisation, $rule]))
        ->assertRedirect();
    expect($rule->refresh()->status)->toBe(OrganisationConfigurationStatus::Active);
});

it('keeps legacy rules behavior and immutable history without a data migration', function () {
    extract(intakeRulesEditorFixture());

    $legacy = app(OrganisationContext::class)->run($organisation, fn (): OrganisationConfiguration => OrganisationConfiguration::factory()->create([
        'area' => OrganisationConfigurationArea::IntakeRules,
        'configuration_key' => 'default',
        'definition' => [
            'required_fields' => ['party_uuid', 'telephone', 'presenting_needs'],
            'default_urgency' => 'urgent',
            'allow_restricted_access_bypass' => false,
        ],
    ]));

    $this->actingAs($administrator)
        ->get(route('intake-rules.index', $organisation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rules.0.requiredFields', ['party_uuid', 'telephone', 'presenting_needs'])
            ->where('rules.0.defaultUrgency', 'urgent'));
    $this->actingAs($administrator)
        ->post(route('intake-rules.store', $organisation), [
            'required_contact_fields' => ['telephone'],
            'default_urgency' => 'urgent',
            'allow_restricted_access_bypass' => false,
        ])
        ->assertSessionHasNoErrors();

    app(OrganisationContext::class)->run($organisation, function () use ($legacy): void {
        $versions = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::IntakeRules)
            ->where('configuration_key', 'default')
            ->orderBy('version')
            ->get();
        expect($versions)->toHaveCount(2)
            ->and($versions[0]->status)->toBe(OrganisationConfigurationStatus::Active)
            ->and($versions[1]->status)->toBe(OrganisationConfigurationStatus::Draft)
            ->and($versions[1]->supersedes_id)->toBe($legacy->id)
            ->and($versions[1]->definition['default_urgency'])->toBe($legacy->definition['default_urgency'])
            ->and(fn () => $versions[0]->update(['definition' => ['changed' => true]]))->toThrow(LogicException::class);
    });
});

it('validates safeguards and removes intake rules from the generic JSON workflow', function () {
    extract(intakeRulesEditorFixture());

    $this->actingAs($administrator)
        ->post(route('intake-rules.store', $organisation), [
            'required_contact_fields' => ['postal_address'],
            'default_urgency' => 'emergency',
            'allow_restricted_access_bypass' => true,
        ])
        ->assertSessionHasErrors(['required_contact_fields.0', 'default_urgency', 'allow_restricted_access_bypass']);
    $this->actingAs($administrator)
        ->get("/{$organisation->slug}/organisation-configurations")
        ->assertNotFound();
    $this->actingAs($administrator)
        ->post("/{$organisation->slug}/organisation-configurations", [
            'area' => 'intake_rules',
            'configuration_key' => 'default',
            'definition_json' => '{}',
        ])
        ->assertNotFound();
    $this->actingAs($officer)
        ->get(route('intake-rules.index', $organisation))
        ->assertForbidden();
});

it('only permits activation of the latest intake rules draft', function () {
    extract(intakeRulesEditorFixture());

    foreach (['routine', 'priority'] as $urgency) {
        $this->actingAs($administrator)
            ->post(route('intake-rules.store', $organisation), [
                'required_contact_fields' => [],
                'default_urgency' => $urgency,
                'allow_restricted_access_bypass' => false,
            ])
            ->assertSessionHasNoErrors();
    }
    $drafts = app(OrganisationContext::class)->run($organisation, fn () => OrganisationConfiguration::query()
        ->where('area', OrganisationConfigurationArea::IntakeRules)
        ->where('configuration_key', 'default')
        ->orderBy('version')
        ->get());
    $this->actingAs($administrator)
        ->get(route('intake-rules.index', $organisation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rules.0.canActivate', true)
            ->where('rules.1.canActivate', false));
    $this->actingAs($administrator)
        ->post("/{$organisation->slug}/organisation-configurations/{$drafts->last()->id}/activate")
        ->assertNotFound();
    $this->actingAs($administrator)
        ->post(route('intake-rules.activate', [$organisation, $drafts->first()]))
        ->assertStatus(409);
});
