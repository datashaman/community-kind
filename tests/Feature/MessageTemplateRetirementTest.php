<?php

use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\OrganisationRole;
use App\Enums\TenantAuditEventType;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{organisation: Organisation, administrator: User, officer: User} */
function messageTemplateRetirementFixture(): array
{
    $organisation = Organisation::factory()->active()->create();
    $administrator = User::factory()->create();
    $officer = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $organisation->memberships()->create(['user_id' => $officer->id, 'role' => OrganisationRole::EngagementOfficer]);

    return compact('organisation', 'administrator', 'officer');
}

function storeMessageTemplate(User $actor, Organisation $organisation, string $body): void
{
    test()->actingAs($actor)
        ->post(route('message-templates.store', $organisation), [
            'name' => 'Welcome email',
            'channel' => 'email',
            'subject' => 'Welcome',
            'body' => $body,
            'journey_kind' => 'general',
        ])
        ->assertSessionHasNoErrors();
}

/** @return Collection<int, OrganisationConfiguration> */
function welcomeEmailVersions(Organisation $organisation)
{
    return app(OrganisationContext::class)->run($organisation, fn () => OrganisationConfiguration::query()
        ->where('area', OrganisationConfigurationArea::MessageTemplate)
        ->where('configuration_key', 'welcome-email')
        ->orderBy('version')
        ->get());
}

it('retires every version still in play and leaves superseded history alone', function () {
    extract(messageTemplateRetirementFixture());

    storeMessageTemplate($administrator, $organisation, 'First body');
    $first = welcomeEmailVersions($organisation)->sole();
    $this->actingAs($administrator)->post(route('message-templates.activate', [$organisation, $first]))->assertRedirect();

    storeMessageTemplate($administrator, $organisation, 'Second body');
    $second = welcomeEmailVersions($organisation)->last();
    $this->actingAs($administrator)->post(route('message-templates.activate', [$organisation, $second]))->assertRedirect();

    // v1 superseded, v2 active. A third draft is left unactivated.
    storeMessageTemplate($administrator, $organisation, 'Third body');

    $this->actingAs($administrator)
        ->post(route('message-templates.retire', [$organisation, 'welcome-email']))
        ->assertRedirect();

    $versions = welcomeEmailVersions($organisation);

    expect($versions->pluck('status')->all())->toBe([
        OrganisationConfigurationStatus::Superseded,
        OrganisationConfigurationStatus::Retired,
        OrganisationConfigurationStatus::Retired,
    ]);
});

it('hides retired templates from the index until they are asked for', function () {
    extract(messageTemplateRetirementFixture());

    storeMessageTemplate($administrator, $organisation, 'First body');
    $this->actingAs($administrator)
        ->post(route('message-templates.retire', [$organisation, 'welcome-email']))
        ->assertRedirect();

    $this->actingAs($administrator)
        ->get(route('message-templates.index', $organisation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('templates', [])
            ->where('retiredCount', 1)
            ->where('showRetired', false)
            ->etc());

    $this->actingAs($administrator)
        ->get(route('message-templates.index', [$organisation, 'retired' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('templates.0.key', 'welcome-email')
            ->where('templates.0.retired', true)
            ->where('showRetired', true)
            ->etc());
});

it('records a tenant audit event for each retired version', function () {
    extract(messageTemplateRetirementFixture());

    storeMessageTemplate($administrator, $organisation, 'First body');
    $this->actingAs($administrator)
        ->post(route('message-templates.retire', [$organisation, 'welcome-email']))
        ->assertRedirect();

    app(OrganisationContext::class)->run($organisation, function (): void {
        $events = TenantAuditEvent::query()
            ->where('type', TenantAuditEventType::OrganisationConfigurationRetired)
            ->get();

        expect($events)->toHaveCount(1)
            ->and($events->first()->payload['configuration_key'])->toBe('welcome-email')
            ->and($events->first()->payload['version'])->toBe(1);
    });
});

it('refuses to retire a template twice', function () {
    extract(messageTemplateRetirementFixture());

    storeMessageTemplate($administrator, $organisation, 'First body');
    $this->actingAs($administrator)
        ->post(route('message-templates.retire', [$organisation, 'welcome-email']))
        ->assertRedirect();
    $this->actingAs($administrator)
        ->post(route('message-templates.retire', [$organisation, 'welcome-email']))
        ->assertStatus(409);
});

it('reinstates a retired template through a new version rather than a status change', function () {
    extract(messageTemplateRetirementFixture());

    storeMessageTemplate($administrator, $organisation, 'First body');
    $this->actingAs($administrator)
        ->post(route('message-templates.retire', [$organisation, 'welcome-email']))
        ->assertRedirect();

    // Retirement is terminal per version: the row itself cannot come back.
    app(OrganisationContext::class)->run($organisation, function () use ($organisation): void {
        $retired = welcomeEmailVersions($organisation)->sole();
        expect(fn () => $retired->update(['status' => OrganisationConfigurationStatus::Active]))
            ->toThrow(LogicException::class);
    });

    storeMessageTemplate($administrator, $organisation, 'Reinstated body');

    $this->actingAs($administrator)
        ->get(route('message-templates.index', $organisation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('templates.0.key', 'welcome-email')
            ->where('templates.0.retired', false)
            ->where('templates.0.versions.0.status', 'draft')
            ->where('templates.0.versions.0.version', 2)
            ->etc());
});

it('does not let a non-administrator retire a template', function () {
    extract(messageTemplateRetirementFixture());

    storeMessageTemplate($administrator, $organisation, 'First body');

    $this->actingAs($officer)
        ->post(route('message-templates.retire', [$organisation, 'welcome-email']))
        ->assertForbidden();

    expect(welcomeEmailVersions($organisation)->sole()->status)
        ->toBe(OrganisationConfigurationStatus::Draft);
});

it('still refuses to delete configuration history', function () {
    extract(messageTemplateRetirementFixture());

    storeMessageTemplate($administrator, $organisation, 'First body');

    app(OrganisationContext::class)->run($organisation, function () use ($organisation): void {
        expect(fn () => welcomeEmailVersions($organisation)->sole()->delete())
            ->toThrow(LogicException::class);
    });
});
