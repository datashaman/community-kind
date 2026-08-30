<?php

use App\Actions\Organisations\PlaceOrganisationAccessHold;
use App\Actions\Organisations\ReleaseOrganisationAccessHold;
use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Enums\OrganisationLifecycleEventType;
use App\Enums\OrganisationStatus;
use App\Models\Organisation;
use App\Models\OrganisationAccessHold;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

it('applies the most restrictive active hold for the requested scope', function () {
    $organisation = Organisation::factory()->active()->create();

    OrganisationAccessHold::factory()->for($organisation)->create([
        'scope' => OrganisationAccessScope::Forms,
        'access_level' => OrganisationAccessLevel::ReadOnly,
    ]);
    OrganisationAccessHold::factory()->for($organisation)->create([
        'scope' => OrganisationAccessScope::All,
        'access_level' => OrganisationAccessLevel::RecoveryOnly,
    ]);
    OrganisationAccessHold::factory()->for($organisation)->create([
        'scope' => OrganisationAccessScope::All,
        'access_level' => OrganisationAccessLevel::Denied,
        'expires_at' => now()->subSecond(),
    ]);

    $resolver = app(ResolveOrganisationAccess::class);

    expect($resolver->handle($organisation, OrganisationAccessScope::Forms))->toBe(OrganisationAccessLevel::RecoveryOnly)
        ->and($resolver->handle($organisation, OrganisationAccessScope::Jobs))->toBe(OrganisationAccessLevel::RecoveryOnly)
        ->and($organisation->fresh()->status)->toBe(OrganisationStatus::Active);
});

it('records hold placement and release while invalidating organisation access', function () {
    $organisation = Organisation::factory()->active()->create();
    $issuer = User::factory()->create();
    $originalVersion = $organisation->fresh()->access_version;

    $hold = app(PlaceOrganisationAccessHold::class)->handle(
        $organisation,
        'security@example.test',
        'Possible account compromise',
        OrganisationAccessScope::All,
        OrganisationAccessLevel::ReadOnly,
        Carbon::now()->addDay(),
        Carbon::now()->addWeek(),
        $issuer,
        '2f968dae-36f7-4bba-a641-548555642391',
    );

    expect($organisation->fresh()->access_version)->toBe($originalVersion + 1)
        ->and($organisation->fresh()->signed_links_invalidated_at)->not->toBeNull()
        ->and($hold->issuer)->toBe('security@example.test')
        ->and($hold->review_at)->not->toBeNull();
    $this->assertDatabaseHas('organisation_lifecycle_events', [
        'organisation_id' => $organisation->id,
        'type' => OrganisationLifecycleEventType::AccessHoldPlaced->value,
    ]);

    app(ReleaseOrganisationAccessHold::class)->handle($hold, 'security@example.test', 'Investigation complete', $issuer);

    expect($hold->fresh()->released_at)->not->toBeNull()
        ->and($organisation->fresh()->access_version)->toBe($originalVersion + 2)
        ->and(app(ResolveOrganisationAccess::class)->handle($organisation, OrganisationAccessScope::Staff))
        ->toBe(OrganisationAccessLevel::Full);
    $this->assertDatabaseHas('organisation_lifecycle_events', [
        'organisation_id' => $organisation->id,
        'type' => OrganisationLifecycleEventType::AccessHoldReleased->value,
    ]);
});

it('enforces staff holds on current organisation routes', function () {
    $owner = User::factory()->create();
    $organisation = $owner->currentOrganisation;
    OrganisationAccessHold::factory()->for($organisation)->create([
        'scope' => OrganisationAccessScope::Staff,
        'access_level' => OrganisationAccessLevel::ReadOnly,
    ]);

    $this->actingAs($owner)->get(route('dashboard', $organisation))->assertForbidden();
    $this
        ->actingAs($owner)
        ->get(route('organisations.edit', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canUpdateOrganisation', false)
            ->where('permissions.canUpdateMember', false)
            ->where('permissions.canCreateInvitation', false)
            ->where('permissions.canTransitionOrganisation', true));
    $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('organisations.update', $organisation), ['name' => 'Blocked rename'])
        ->assertForbidden();
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
