<?php

use App\Actions\Configuration\ActivateOrganisationConfiguration;
use App\Actions\Configuration\CreateOrganisationConfiguration;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\OrganisationRole;
use App\Models\AudienceSegment;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\SupporterJourney;
use App\Models\User;
use App\OrganisationContext;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{organisation: Organisation, administrator: User, officer: User} */
function supporterJourneyPolicyEditorFixture(): array
{
    $organisation = Organisation::factory()->active()->create();
    $administrator = User::factory()->create();
    $officer = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $organisation->memberships()->create(['user_id' => $officer->id, 'role' => OrganisationRole::EngagementOfficer]);

    return compact('organisation', 'administrator', 'officer');
}

it('creates activates and applies a typed journey policy with an explicit template', function () {
    extract(supporterJourneyPolicyEditorFixture());

    [$template, $segment] = app(OrganisationContext::class)->run($organisation, fn (): array => [
        OrganisationConfiguration::factory()->create([
            'area' => OrganisationConfigurationArea::MessageTemplate,
            'configuration_key' => 'welcome_email',
            'definition' => [
                'channel' => 'email',
                'subject' => 'Welcome, {{ supporter_name }}',
                'body' => 'Thank you for joining our community.',
                'journey_kind' => 'general',
            ],
        ]),
        AudienceSegment::factory()->create(),
    ]);

    $this->actingAs($administrator)
        ->get(route('supporter-journey-policy.index', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('supporter-journey-policy/index')
            ->where('templates.0.key', 'welcome_email')
            ->where('templates.0.name', 'Welcome Email')
            ->where('templates.0.version', 1));

    $this->actingAs($administrator)
        ->post(route('supporter-journey-policy.store', $organisation), [
            'default_kind' => 'general',
            'default_channel' => 'email',
            'default_message_template_id' => $template->id,
            'require_approval' => true,
            'dispatch_rechecks_consent' => true,
            'frequency_cap_days' => 14,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $policy = app(OrganisationContext::class)->run($organisation, fn (): OrganisationConfiguration => OrganisationConfiguration::query()
        ->where('area', OrganisationConfigurationArea::SupporterJourney)
        ->where('configuration_key', 'default')
        ->sole());
    expect($policy->status)->toBe(OrganisationConfigurationStatus::Draft)
        ->and($policy->definition)->toBe([
            'default_kind' => 'general',
            'default_channel' => 'email',
            'default_message_template_id' => $template->id,
            'require_approval' => true,
            'dispatch_rechecks_consent' => true,
            'frequency_cap_days' => 14,
        ]);

    $this->actingAs($administrator)
        ->post(route('supporter-journey-policy.activate', [$organisation, $policy]))
        ->assertRedirect();
    app(OrganisationContext::class)->run($organisation, function () use ($administrator, $organisation): void {
        $replacement = app(CreateOrganisationConfiguration::class)->handle($organisation, OrganisationConfigurationArea::MessageTemplate, 'welcome_email', [
            'channel' => 'email',
            'subject' => 'A replacement subject',
            'body' => 'Replacement template content.',
            'journey_kind' => 'general',
        ], $administrator);
        app(ActivateOrganisationConfiguration::class)->handle($replacement, $administrator);
    });
    $this->actingAs($officer)
        ->get(route('supporter-journeys.index', $organisation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('policyDefaults.templateId', $template->id)
            ->where('templates.0.name', 'Welcome Email')
            ->where('templates.0.version', 2));
    $this->actingAs($administrator)
        ->get(route('supporter-journey-policy.index', $organisation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('policies.0.defaultMessageTemplateId', $template->id)
            ->where('templates.1.id', $template->id)
            ->where('templates.1.status', 'superseded'));
    $this->actingAs($officer)
        ->post(route('supporter-journeys.store', $organisation), [
            'audience_segment_id' => $segment->id,
            'name' => 'Policy-backed welcome',
            'experiment_enabled' => false,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    app(OrganisationContext::class)->run($organisation, function () use ($template): void {
        $journey = SupporterJourney::query()->where('name', 'Policy-backed welcome')->sole();
        expect($journey->journey_kind->value)->toBe($template->definition['journey_kind'])
            ->and($journey->channel)->toBe($template->definition['channel'])
            ->and($journey->subject)->toBe($template->definition['subject'])
            ->and($journey->body)->toBe($template->definition['body']);
    });

    $this->actingAs($officer)
        ->post(route('supporter-journeys.store', $organisation), [
            'audience_segment_id' => $segment->id,
            'message_template_id' => '__custom__',
            'name' => 'Custom policy-backed welcome',
            'channel' => 'email',
            'subject' => 'A custom subject',
            'body' => 'A custom message.',
            'experiment_enabled' => false,
        ])
        ->assertSessionHasNoErrors();
    app(OrganisationContext::class)->run($organisation, function (): void {
        $journey = SupporterJourney::query()->where('name', 'Custom policy-backed welcome')->sole();
        expect($journey->subject)->toBe('A custom subject')
            ->and($journey->body)->toBe('A custom message.');
    });
});

it('preserves legacy policy behavior and immutable version history without migration', function () {
    extract(supporterJourneyPolicyEditorFixture());

    $legacy = app(OrganisationContext::class)->run($organisation, fn (): OrganisationConfiguration => OrganisationConfiguration::factory()->create([
        'area' => OrganisationConfigurationArea::SupporterJourney,
        'configuration_key' => 'default',
        'definition' => [
            'default_kind' => 're_engagement',
            'default_channel' => 'sms',
            'require_approval' => true,
            'dispatch_rechecks_consent' => true,
            'frequency_cap_days' => 21,
        ],
    ]));

    $this->actingAs($administrator)
        ->get(route('supporter-journey-policy.index', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('policies.0.version', 1)
            ->where('policies.0.defaultMessageTemplateId', null)
            ->where('policies.0.frequencyCapDays', 21));
    $this->actingAs($administrator)
        ->post(route('supporter-journey-policy.store', $organisation), [
            'default_kind' => 're_engagement',
            'default_channel' => 'sms',
            'default_message_template_id' => '',
            'require_approval' => true,
            'dispatch_rechecks_consent' => true,
            'frequency_cap_days' => 30,
        ])
        ->assertSessionHasNoErrors();

    app(OrganisationContext::class)->run($organisation, function () use ($legacy): void {
        $versions = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::SupporterJourney)
            ->where('configuration_key', 'default')
            ->orderBy('version')
            ->get();
        expect($versions)->toHaveCount(2)
            ->and($versions[0]->status)->toBe(OrganisationConfigurationStatus::Active)
            ->and($versions[1]->status)->toBe(OrganisationConfigurationStatus::Draft)
            ->and($versions[1]->supersedes_id)->toBe($legacy->id)
            ->and(fn () => $versions[0]->update(['definition' => ['changed' => true]]))->toThrow(LogicException::class);
    });
});

it('validates safeguards and removes journey policy from the generic JSON workflow', function () {
    extract(supporterJourneyPolicyEditorFixture());

    [$template, $draftPolicy] = app(OrganisationContext::class)->run($organisation, fn (): array => [
        OrganisationConfiguration::factory()->create([
            'area' => OrganisationConfigurationArea::MessageTemplate,
            'configuration_key' => 'sms_re_engagement',
            'definition' => ['channel' => 'sms', 'subject' => null, 'body' => 'We miss you.', 'journey_kind' => 're_engagement'],
        ]),
        OrganisationConfiguration::factory()->create([
            'area' => OrganisationConfigurationArea::SupporterJourney,
            'configuration_key' => 'default',
            'status' => OrganisationConfigurationStatus::Draft,
            'activated_at' => null,
            'definition' => [
                'default_kind' => 'general',
                'default_channel' => 'email',
                'require_approval' => true,
                'dispatch_rechecks_consent' => true,
                'frequency_cap_days' => 7,
            ],
        ]),
    ]);

    $this->actingAs($administrator)
        ->post(route('supporter-journey-policy.store', $organisation), [
            'default_kind' => 'general',
            'default_channel' => 'email',
            'default_message_template_id' => $template->id,
            'require_approval' => false,
            'dispatch_rechecks_consent' => false,
            'frequency_cap_days' => 1,
        ])
        ->assertSessionHasErrors([
            'default_message_template_id',
            'require_approval',
            'dispatch_rechecks_consent',
            'frequency_cap_days',
        ]);
    $this->actingAs($administrator)
        ->post(route('supporter-journey-policy.store', $organisation), [
            'default_kind' => 'general',
            'default_channel' => 'email',
            'default_message_template_id' => 'not-a-uuid',
            'require_approval' => true,
            'dispatch_rechecks_consent' => true,
            'frequency_cap_days' => 14,
        ])
        ->assertSessionHasErrors('default_message_template_id');
    $this->actingAs($administrator)
        ->get("/{$organisation->slug}/organisation-configurations")
        ->assertNotFound();
    $this->actingAs($administrator)
        ->post("/{$organisation->slug}/organisation-configurations", [
            'area' => 'supporter_journey',
            'configuration_key' => 'default',
            'definition_json' => '{}',
        ])
        ->assertNotFound();
    $this->actingAs($administrator)
        ->post("/{$organisation->slug}/organisation-configurations/{$draftPolicy->id}/activate")
        ->assertNotFound();
    $this->actingAs($officer)
        ->get(route('supporter-journey-policy.index', $organisation))
        ->assertForbidden();
});

it('only offers activation for the latest journey policy draft', function () {
    extract(supporterJourneyPolicyEditorFixture());

    foreach ([14, 21] as $frequencyCapDays) {
        $this->actingAs($administrator)
            ->post(route('supporter-journey-policy.store', $organisation), [
                'default_kind' => 'general',
                'default_channel' => 'email',
                'default_message_template_id' => '',
                'require_approval' => true,
                'dispatch_rechecks_consent' => true,
                'frequency_cap_days' => $frequencyCapDays,
            ])
            ->assertSessionHasNoErrors();
    }

    $drafts = app(OrganisationContext::class)->run($organisation, fn () => OrganisationConfiguration::query()
        ->where('area', OrganisationConfigurationArea::SupporterJourney)
        ->where('configuration_key', 'default')
        ->orderBy('version')
        ->get());
    $this->actingAs($administrator)
        ->get(route('supporter-journey-policy.index', $organisation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('policies.0.canActivate', true)
            ->where('policies.1.canActivate', false));
    $this->actingAs($administrator)
        ->post(route('supporter-journey-policy.activate', [$organisation, $drafts->first()]))
        ->assertStatus(409);
});
