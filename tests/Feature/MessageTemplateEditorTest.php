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
function messageTemplateEditorFixture(): array
{
    $organisation = Organisation::factory()->active()->create();
    $administrator = User::factory()->create();
    $officer = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $organisation->memberships()->create(['user_id' => $officer->id, 'role' => OrganisationRole::EngagementOfficer]);

    return compact('organisation', 'administrator', 'officer');
}

it('creates previews activates and versions message templates without JSON', function () {
    extract(messageTemplateEditorFixture());

    $this->actingAs($administrator)
        ->post(route('message-templates.store', $organisation), [
            'name' => 'Donor re-engagement',
            'channel' => 'sms',
            'subject' => 'This stale email subject must not be stored',
            'body' => 'Hello {{ supporter_name }}, thank you for your {{ donation_count }} gifts.',
            'journey_kind' => 're_engagement',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $first = app(OrganisationContext::class)->run($organisation, fn (): OrganisationConfiguration => OrganisationConfiguration::query()
        ->where('area', OrganisationConfigurationArea::MessageTemplate)
        ->where('configuration_key', 'donor-re-engagement')
        ->sole());
    expect($first->version)->toBe(1)
        ->and($first->status)->toBe(OrganisationConfigurationStatus::Draft)
        ->and($first->definition)->toBe([
            'channel' => 'sms',
            'subject' => null,
            'body' => 'Hello {{ supporter_name }}, thank you for your {{ donation_count }} gifts.',
            'journey_kind' => 're_engagement',
        ]);

    $this->actingAs($administrator)
        ->get(route('message-templates.index', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('message-templates/index')
            ->where('templates.0.name', 'Donor Re Engagement')
            ->where('templates.0.version', 1)
            ->where('templates.0.body', 'Hello {{ supporter_name }}, thank you for your {{ donation_count }} gifts.')
            ->where('templates.0.status', 'draft'));

    $this->actingAs($administrator)
        ->post(route('message-templates.activate', [$organisation, $first]))
        ->assertRedirect();
    $this->actingAs($administrator)
        ->post(route('message-templates.store', $organisation), [
            'name' => 'Donor re-engagement',
            'channel' => 'email',
            'subject' => 'We miss you, {{ supporter_name }}',
            'body' => 'You have supported us {{ donation_count }} times.',
            'journey_kind' => 're_engagement',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    app(OrganisationContext::class)->run($organisation, function () use ($first): void {
        $versions = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::MessageTemplate)
            ->where('configuration_key', 'donor-re-engagement')
            ->orderBy('version')
            ->get();
        expect($versions)->toHaveCount(2)
            ->and($versions[0]->refresh()->status)->toBe(OrganisationConfigurationStatus::Active)
            ->and($versions[1]->version)->toBe(2)
            ->and($versions[1]->supersedes_id)->toBe($first->id)
            ->and(fn () => $versions[0]->update(['definition' => ['changed' => true]]))->toThrow(LogicException::class);
    });
});

it('returns channel and variable validation errors on their fields', function () {
    extract(messageTemplateEditorFixture());

    $this->actingAs($administrator)
        ->post(route('message-templates.store', $organisation), [
            'name' => 'Invalid email',
            'channel' => 'email',
            'subject' => '',
            'body' => 'Hello {{ secret_value }}',
            'journey_kind' => 'general',
        ])
        ->assertSessionHasErrors(['subject', 'body']);

    $this->actingAs($administrator)
        ->post(route('message-templates.store', $organisation), [
            'name' => 'Long SMS',
            'channel' => 'sms',
            'subject' => '',
            'body' => str_repeat('x', 481),
            'journey_kind' => 'general',
        ])
        ->assertSessionHasErrors('body');
});

it('removes message templates from the generic JSON workflow and enforces administrator access', function () {
    extract(messageTemplateEditorFixture());

    app(OrganisationContext::class)->run($organisation, fn () => OrganisationConfiguration::factory()->create([
        'area' => OrganisationConfigurationArea::MessageTemplate,
        'configuration_key' => 'historical_template',
        'definition' => ['channel' => 'sms', 'subject' => null, 'body' => 'Historical body', 'journey_kind' => 'general'],
    ]));

    $this->actingAs($administrator)
        ->get(route('organisation-configurations.index', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('areas', fn ($areas) => collect($areas)->doesntContain('value', 'message_template'))
            ->where('configurations', fn ($configurations) => collect($configurations)->doesntContain('area', 'message_template')));
    $this->actingAs($administrator)
        ->post(route('organisation-configurations.store', $organisation), [
            'area' => 'message_template',
            'configuration_key' => 'raw-json',
            'definition_json' => json_encode(['channel' => 'sms'], JSON_THROW_ON_ERROR),
        ])
        ->assertSessionHasErrors('area');
    $historicalTemplate = app(OrganisationContext::class)->run($organisation, fn (): OrganisationConfiguration => OrganisationConfiguration::query()
        ->where('area', OrganisationConfigurationArea::MessageTemplate)
        ->where('configuration_key', 'historical_template')
        ->sole());
    $this->actingAs($administrator)
        ->post(route('organisation-configurations.activate', [$organisation, $historicalTemplate]))
        ->assertNotFound();
    $this->actingAs($administrator)
        ->post(route('message-templates.store', $organisation), [
            'template_key' => 'historical_template',
            'name' => 'Historical Template',
            'channel' => 'sms',
            'subject' => '',
            'body' => 'A new version of the historical body',
            'journey_kind' => 'general',
        ])
        ->assertSessionHasNoErrors();
    app(OrganisationContext::class)->run($organisation, function (): void {
        expect(OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::MessageTemplate)
            ->where('configuration_key', 'historical_template')
            ->max('version'))->toBe(2);
    });
    $this->actingAs($officer)
        ->get(route('message-templates.index', $organisation))
        ->assertForbidden();
});
