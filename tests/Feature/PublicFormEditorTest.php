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
function publicFormEditorFixture(): array
{
    $organisation = Organisation::factory()->active()->create();
    $administrator = User::factory()->create();
    $officer = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $organisation->memberships()->create(['user_id' => $officer->id, 'role' => OrganisationRole::CaseWorker]);

    return compact('organisation', 'administrator', 'officer');
}

it('creates previews and activates an ordered purpose-built public form', function () {
    extract(publicFormEditorFixture());

    $this->actingAs($administrator)
        ->post(route('public-forms.store', $organisation), [
            'form' => 'volunteer_registration',
            'ordered_fields' => ['email', 'name', 'availability', 'interests'],
            'required_fields' => ['interests'],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $form = app(OrganisationContext::class)->run($organisation, fn (): OrganisationConfiguration => OrganisationConfiguration::query()
        ->where('area', OrganisationConfigurationArea::PublicForm)
        ->where('configuration_key', 'volunteer_registration')
        ->sole());

    expect($form->definition)->toBe([
        'form' => 'volunteer_registration',
        'required_fields' => ['email', 'name', 'availability', 'interests'],
        'fields' => [
            ['key' => 'email', 'type' => 'email', 'required' => true],
            ['key' => 'name', 'type' => 'text', 'required' => true],
            ['key' => 'availability', 'type' => 'multiselect', 'required' => true],
            ['key' => 'interests', 'type' => 'multiselect', 'required' => true],
        ],
    ])->and($form->status)->toBe(OrganisationConfigurationStatus::Draft);

    $this->actingAs($administrator)
        ->get(route('public-forms.index', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public-forms/index')
            ->where('forms.0.purpose', 'volunteer_registration')
            ->where('forms.0.fields.0.key', 'email')
            ->where('forms.0.fields.3.required', true)
            ->where('forms.0.canActivate', true));

    $this->actingAs($administrator)
        ->post(route('public-forms.activate', [$organisation, $form]))
        ->assertRedirect();
    expect($form->refresh()->status)->toBe(OrganisationConfigurationStatus::Active);
});

it('derives the structured editor state from legacy public form definitions', function () {
    extract(publicFormEditorFixture());

    $legacy = app(OrganisationContext::class)->run($organisation, fn (): OrganisationConfiguration => OrganisationConfiguration::factory()->create([
        'area' => OrganisationConfigurationArea::PublicForm,
        'configuration_key' => 'legacy_supporter_details',
        'definition' => [
            'form' => 'supporter_profile',
            'required_fields' => ['name', 'email', 'telephone'],
        ],
    ]));

    $this->actingAs($administrator)
        ->get(route('public-forms.index', $organisation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('forms.0.id', $legacy->id)
            ->where('forms.0.purpose', 'supporter_profile')
            ->where('forms.0.purposeLabel', 'Supporter profile')
            ->where('forms.0.fields', [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'fixedRequired' => true],
                ['key' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'fixedRequired' => true],
                ['key' => 'telephone', 'label' => 'Telephone number', 'type' => 'tel', 'required' => true, 'fixedRequired' => false],
            ]));

    expect($legacy->refresh()->definition)->not->toHaveKey('fields');
});

it('rejects malformed form definitions and the generic JSON workflow', function () {
    extract(publicFormEditorFixture());

    $this->actingAs($administrator)
        ->post(route('public-forms.store', $organisation), [
            'form' => 'event_registration',
            'ordered_fields' => ['name', 'name'],
            'required_fields' => ['organisation_id'],
        ])
        ->assertSessionHasErrors(['ordered_fields.1', 'ordered_fields', 'required_fields']);
    $this->actingAs($administrator)
        ->get("/{$organisation->slug}/organisation-configurations")
        ->assertNotFound();
    $this->actingAs($administrator)
        ->post("/{$organisation->slug}/organisation-configurations", [
            'area' => 'public_form',
            'configuration_key' => 'event_registration',
            'definition_json' => '{"form":"event_registration","required_fields":["name","email"]}',
        ])
        ->assertNotFound();
    $this->actingAs($officer)
        ->get(route('public-forms.index', $organisation))
        ->assertForbidden();
});

it('only permits activation of the latest draft for each public form purpose', function () {
    extract(publicFormEditorFixture());

    foreach ([['name', 'email'], ['email', 'name']] as $orderedFields) {
        $this->actingAs($administrator)
            ->post(route('public-forms.store', $organisation), [
                'form' => 'event_registration',
                'ordered_fields' => $orderedFields,
                'required_fields' => [],
            ])
            ->assertSessionHasNoErrors();
    }
    $drafts = app(OrganisationContext::class)->run($organisation, fn () => OrganisationConfiguration::query()
        ->where('area', OrganisationConfigurationArea::PublicForm)
        ->where('configuration_key', 'event_registration')
        ->orderBy('version')
        ->get());

    $this->actingAs($administrator)
        ->post("/{$organisation->slug}/organisation-configurations/{$drafts->last()->id}/activate")
        ->assertNotFound();
    $this->actingAs($administrator)
        ->post(route('public-forms.activate', [$organisation, $drafts->first()]))
        ->assertStatus(409);
});
