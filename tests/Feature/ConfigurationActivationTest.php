<?php

use App\Actions\Configuration\ActivateOrganisationConfiguration;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\User;
use App\OrganisationContext;

/**
 * Activation supersedes the version currently in use. It must touch nothing
 * else: versions already superseded are settled history, and any other status
 * is not activation's business.
 */
it('supersedes only the active version and leaves other statuses alone', function () {
    $organisation = Organisation::factory()->active()->create();
    $administrator = User::factory()->create();
    $organisation->memberships()->create([
        'user_id' => $administrator->id,
        'role' => OrganisationRole::OrganisationAdministrator,
    ]);

    app(OrganisationContext::class)->run($organisation, function () use ($administrator): void {
        $superseded = OrganisationConfiguration::factory()->create([
            'area' => OrganisationConfigurationArea::Reporting,
            'configuration_key' => 'impact',
            'version' => 1,
            'status' => OrganisationConfigurationStatus::Superseded,
        ]);
        $active = OrganisationConfiguration::factory()->create([
            'area' => OrganisationConfigurationArea::Reporting,
            'configuration_key' => 'impact',
            'version' => 2,
            'status' => OrganisationConfigurationStatus::Active,
        ]);
        $draft = OrganisationConfiguration::factory()->create([
            'area' => OrganisationConfigurationArea::Reporting,
            'configuration_key' => 'impact',
            'version' => 3,
            'status' => OrganisationConfigurationStatus::Draft,
            'activated_at' => null,
        ]);

        app(ActivateOrganisationConfiguration::class)->handle($draft, $administrator);

        expect($draft->fresh()->status)->toBe(OrganisationConfigurationStatus::Active)
            ->and($active->fresh()->status)->toBe(OrganisationConfigurationStatus::Superseded)
            ->and($superseded->fresh()->status)->toBe(OrganisationConfigurationStatus::Superseded);
    });
});

it('does not touch another configuration key while superseding', function () {
    $organisation = Organisation::factory()->active()->create();
    $administrator = User::factory()->create();
    $organisation->memberships()->create([
        'user_id' => $administrator->id,
        'role' => OrganisationRole::OrganisationAdministrator,
    ]);

    app(OrganisationContext::class)->run($organisation, function () use ($administrator): void {
        $otherKeyActive = OrganisationConfiguration::factory()->create([
            'area' => OrganisationConfigurationArea::Reporting,
            'configuration_key' => 'other-impact',
            'version' => 1,
            'status' => OrganisationConfigurationStatus::Active,
        ]);
        $draft = OrganisationConfiguration::factory()->create([
            'area' => OrganisationConfigurationArea::Reporting,
            'configuration_key' => 'impact',
            'version' => 1,
            'status' => OrganisationConfigurationStatus::Draft,
            'activated_at' => null,
        ]);

        app(ActivateOrganisationConfiguration::class)->handle($draft, $administrator);

        expect($draft->fresh()->status)->toBe(OrganisationConfigurationStatus::Active)
            ->and($otherKeyActive->fresh()->status)->toBe(OrganisationConfigurationStatus::Active);
    });
});
