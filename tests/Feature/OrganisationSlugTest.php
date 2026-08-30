<?php

use App\Actions\Organisations\ChangeOrganisationSlug;
use App\Enums\OrganisationLifecycleEventType;
use App\Models\Organisation;
use App\Models\User;

it('keeps the organisation slug stable when its display name changes', function () {
    $owner = User::factory()->create();
    $organisation = $owner->currentOrganisation;
    $originalSlug = $organisation->slug;

    $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('organisations.update', $organisation), ['name' => 'A completely new name'])
        ->assertRedirect(route('organisations.edit', $organisation));

    expect($organisation->fresh()->slug)->toBe($originalSlug);
});

it('changes a slug explicitly and reserves the previous slug', function () {
    $this->travelTo(now()->startOfSecond());

    $owner = User::factory()->create();
    $organisation = $owner->currentOrganisation;
    $oldSlug = $organisation->slug;
    $originalVersion = $organisation->fresh()->access_version;

    $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('organisations.slug.update', $organisation), ['slug' => 'new-community-name'])
        ->assertRedirect(route('organisations.edit', $organisation->fresh()));

    expect($organisation->fresh()->slug)->toBe('new-community-name')
        ->and($organisation->fresh()->access_version)->toBe($originalVersion + 1);
    $this->assertDatabaseHas('organisation_slugs', [
        'organisation_id' => $organisation->id,
        'slug' => $oldSlug,
        'redirect_until' => now()->addDays(30),
        'quarantined_until' => now()->addDays(120),
    ]);
    $this->assertDatabaseHas('organisation_lifecycle_events', [
        'organisation_id' => $organisation->id,
        'type' => OrganisationLifecycleEventType::SlugChanged->value,
    ]);

    $this
        ->actingAs($owner)
        ->get("/settings/organisations/{$oldSlug}?tab=members")
        ->assertRedirect('/settings/organisations/new-community-name?tab=members');
    $this
        ->actingAs($owner)
        ->get("/{$oldSlug}/dashboard")
        ->assertRedirect('/new-community-name/dashboard');

    $otherOwner = User::factory()->create();
    $this
        ->actingAs($otherOwner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('organisations.slug.update', $otherOwner->currentOrganisation), ['slug' => $oldSlug])
        ->assertSessionHasErrors('slug');
});

it('releases a quarantined slug after its reservation expires', function () {
    $firstOwner = User::factory()->create();
    $firstOrganisation = $firstOwner->currentOrganisation;
    $oldSlug = $firstOrganisation->slug;

    $this
        ->actingAs($firstOwner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('organisations.slug.update', $firstOrganisation), ['slug' => 'replacement-slug']);

    $this->travel(121)->days();
    $secondOwner = User::factory()->create();

    app(ChangeOrganisationSlug::class)->handle($secondOwner->currentOrganisation, $oldSlug, $secondOwner);

    expect($secondOwner->currentOrganisation->fresh()->slug)->toBe($oldSlug);
});

it('does not assign a quarantined slug when creating an organisation', function () {
    $owner = User::factory()->create();
    $organisation = Organisation::factory()->active()->create([
        'name' => 'Old Community',
        'slug' => 'old-community',
    ]);
    $organisation->members()->attach($owner, ['is_owner' => true]);
    app(ChangeOrganisationSlug::class)->handle($organisation, 'replacement-community', $owner);

    $newOwner = User::factory()->create();
    $this
        ->actingAs($newOwner)
        ->post(route('organisations.store'), ['name' => 'Old Community'])
        ->assertRedirect();

    $this->assertDatabaseHas('organisations', [
        'name' => 'Old Community',
        'slug' => 'old-community-1',
    ]);
});

it('does not redirect a previous slug after its organisation is deleted', function () {
    $owner = User::factory()->create();
    $organisation = $owner->currentOrganisation;
    $oldSlug = $organisation->slug;
    app(ChangeOrganisationSlug::class)->handle($organisation, 'new-deleted-community', $owner);
    $organisation->delete();

    $this
        ->actingAs($owner)
        ->get("/settings/organisations/{$oldSlug}")
        ->assertNotFound();
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
