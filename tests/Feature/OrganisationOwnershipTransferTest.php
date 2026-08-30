<?php

use App\Enums\OrganisationLifecycleEventType;
use App\Enums\OrganisationOwnershipTransferStatus;
use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationOwnershipTransfer;
use App\Models\User;

it('requires explicit acceptance before transferring the sole ownership', function () {
    $owner = User::factory()->create();
    $nominee = User::factory()->create();
    $organisation = $owner->currentOrganisation;
    $organisation->memberships()->create([
        'user_id' => $nominee->id,
        'role' => OrganisationRole::OrganisationAdministrator,
    ]);

    $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->post(route('organisations.ownership-transfers.store', $organisation), [
            'nominee_user_id' => $nominee->id,
        ])
        ->assertRedirect(route('organisations.edit', $organisation));

    $transfer = OrganisationOwnershipTransfer::query()->sole();
    expect($transfer->status)->toBe(OrganisationOwnershipTransferStatus::Pending)
        ->and($owner->fresh()->ownsOrganisation($organisation))->toBeTrue()
        ->and($nominee->fresh()->ownsOrganisation($organisation))->toBeFalse();

    $this
        ->actingAs($nominee)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('organisations.ownership-transfers.update', [$organisation, $transfer]))
        ->assertRedirect(route('organisations.edit', $organisation));

    expect($transfer->fresh()->status)->toBe(OrganisationOwnershipTransferStatus::Accepted)
        ->and($owner->fresh()->ownsOrganisation($organisation))->toBeFalse()
        ->and($nominee->fresh()->ownsOrganisation($organisation))->toBeTrue();
    $this->assertDatabaseHas('organisation_lifecycle_events', [
        'organisation_id' => $organisation->id,
        'type' => OrganisationLifecycleEventType::OwnershipTransferAccepted->value,
        'actor_user_id' => $nominee->id,
    ]);
});

it('does not let another member accept a transfer', function () {
    $owner = User::factory()->create();
    $nominee = User::factory()->create();
    $otherMember = User::factory()->create();
    $organisation = $owner->currentOrganisation;
    $organisation->members()->attach($nominee);
    $organisation->members()->attach($otherMember);
    $transfer = OrganisationOwnershipTransfer::factory()->for($organisation)->create([
        'nominated_by_user_id' => $owner->id,
        'nominee_user_id' => $nominee->id,
    ]);

    $this
        ->actingAs($otherMember)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('organisations.ownership-transfers.update', [$organisation, $transfer]))
        ->assertForbidden();

    expect($transfer->fresh()->status)->toBe(OrganisationOwnershipTransferStatus::Pending)
        ->and($owner->fresh()->ownsOrganisation($organisation))->toBeTrue();
});

it('rejects acceptance after the nomination expires', function () {
    $owner = User::factory()->create();
    $nominee = User::factory()->create();
    $organisation = $owner->currentOrganisation;
    $organisation->members()->attach($nominee);
    $transfer = OrganisationOwnershipTransfer::factory()->for($organisation)->create([
        'nominated_by_user_id' => $owner->id,
        'nominee_user_id' => $nominee->id,
        'expires_at' => now()->subSecond(),
    ]);

    $this
        ->actingAs($nominee)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('organisations.ownership-transfers.update', [$organisation, $transfer]))
        ->assertSessionHasErrors('transfer');

    expect($owner->fresh()->ownsOrganisation($organisation))->toBeTrue()
        ->and($nominee->fresh()->ownsOrganisation($organisation))->toBeFalse();
});

it('lets an owner leave when another owner remains', function () {
    $firstOwner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $organisation->members()->attach($firstOwner, ['is_owner' => true]);
    $organisation->members()->attach($secondOwner, ['is_owner' => true]);

    $this
        ->actingAs($firstOwner)
        ->delete(route('organisations.leave', $organisation))
        ->assertRedirect(route('organisations.index'));

    expect($firstOwner->fresh()->belongsToOrganisation($organisation))->toBeFalse()
        ->and($secondOwner->fresh()->ownsOrganisation($organisation))->toBeTrue();
});

it('lets an owner remove another owner only when an owner remains', function () {
    $firstOwner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $organisation->members()->attach($firstOwner, ['is_owner' => true]);
    $organisation->members()->attach($secondOwner, ['is_owner' => true]);

    $this
        ->actingAs($firstOwner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete(route('organisations.members.destroy', [$organisation, $secondOwner]))
        ->assertRedirect(route('organisations.edit', $organisation));

    expect($secondOwner->fresh()->belongsToOrganisation($organisation))->toBeFalse()
        ->and($firstOwner->fresh()->ownsOrganisation($organisation))->toBeTrue();

    $this
        ->actingAs($firstOwner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete(route('organisations.members.destroy', [$organisation, $firstOwner]))
        ->assertForbidden();
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
