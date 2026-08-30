<?php

use App\Enums\OrganisationRole;
use App\Enums\OrganisationStatus;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('creates a pending organisation with a separate owner membership', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('organisations.store'), ['name' => 'Harbour Kind']);

    $organisation = Organisation::query()->where('slug', 'harbour-kind')->firstOrFail();

    $response->assertRedirect(route('organisations.edit', $organisation));
    expect($user->fresh()->current_organisation_id)->toBe($organisation->id);
    $this->assertDatabaseHas('organisations', [
        'id' => $organisation->id,
        'name' => 'Harbour Kind',
        'slug' => 'harbour-kind',
        'status' => OrganisationStatus::Pending->value,
    ]);
    $this->assertDatabaseHas('organisation_members', [
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'is_owner' => true,
        'role' => null,
    ]);
});

it('does not retain the legacy personal tenant column', function () {
    expect(Schema::hasColumn('organisations', 'is_personal'))->toBeFalse();
});

it('assigns one operational role and program access independently of ownership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $housing = Program::factory()->for($organisation)->create(['name' => 'Housing']);
    $food = Program::factory()->for($organisation)->create(['name' => 'Food']);

    $organisation->memberships()->create([
        'user_id' => $owner->id,
        'is_owner' => true,
        'role' => null,
    ]);
    $organisation->memberships()->create([
        'user_id' => $member->id,
        'role' => OrganisationRole::CaseWorker,
    ]);

    $response = $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('organisations.members.update', [$organisation, $member]), [
            'role' => OrganisationRole::ProgramManager->value,
            'program_ids' => [$housing->id],
        ]);

    $response->assertRedirect(route('organisations.edit', $organisation));
    expect($owner->fresh()->ownsOrganisation($organisation))->toBeTrue()
        ->and($owner->organisationRole($organisation))->toBeNull()
        ->and($owner->hasProgramAccess($housing))->toBeFalse()
        ->and($member->fresh()->organisationRole($organisation))->toBe(OrganisationRole::ProgramManager)
        ->and($member->hasProgramAccess($housing))->toBeTrue()
        ->and($member->hasProgramAccess($food))->toBeFalse();
});

it('lets organisation administrators manage member roles without granting ownership', function () {
    $administrator = User::factory()->create();
    $member = User::factory()->create();
    $organisation = Organisation::factory()->create();

    $organisation->memberships()->create([
        'user_id' => $administrator->id,
        'role' => OrganisationRole::OrganisationAdministrator,
    ]);
    $organisation->memberships()->create([
        'user_id' => $member->id,
        'role' => OrganisationRole::CaseWorker,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('organisations.members.update', [$organisation, $member]), [
            'role' => OrganisationRole::ExecutiveViewer->value,
        ]);

    $response->assertRedirect(route('organisations.edit', $organisation));
    expect($administrator->ownsOrganisation($organisation))->toBeFalse()
        ->and($member->fresh()->organisationRole($organisation))->toBe(OrganisationRole::ExecutiveViewer);
});

it('rejects program access from another organisation without changing the membership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $otherProgram = Program::factory()->for($otherOrganisation)->create();

    $organisation->memberships()->create([
        'user_id' => $owner->id,
        'is_owner' => true,
    ]);
    $membership = $organisation->memberships()->create([
        'user_id' => $member->id,
        'role' => OrganisationRole::CaseWorker,
    ]);

    $response = $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->from(route('organisations.edit', $organisation))
        ->patch(route('organisations.members.update', [$organisation, $member]), [
            'role' => OrganisationRole::ProgramManager->value,
            'program_ids' => [$otherProgram->id],
        ]);

    $response
        ->assertRedirect(route('organisations.edit', $organisation))
        ->assertSessionHasErrors([
            'program_ids.0' => 'The selected program is not available for this organisation.',
        ]);
    expect($membership->fresh()->role)->toBe(OrganisationRole::CaseWorker)
        ->and($membership->programs()->count())->toBe(0);
});

it('switches to a fresh destination dashboard and recomputes organisation context', function () {
    $user = User::factory()->create();
    $firstOrganisation = Organisation::factory()->create(['name' => 'HarbourKind']);
    $secondOrganisation = Organisation::factory()->create(['name' => 'NeighbourLink']);
    $firstProgram = Program::factory()->for($firstOrganisation)->create();
    $secondProgram = Program::factory()->for($secondOrganisation)->create();

    $firstMembership = $firstOrganisation->memberships()->create([
        'user_id' => $user->id,
        'role' => OrganisationRole::ProgramManager,
    ]);
    $firstMembership->programs()->attach($firstProgram);
    $secondMembership = $secondOrganisation->memberships()->create([
        'user_id' => $user->id,
        'role' => OrganisationRole::ExecutiveViewer,
    ]);
    $secondMembership->programs()->attach($secondProgram);
    $user->switchOrganisation($firstOrganisation);

    $response = $this
        ->actingAs($user)
        ->post(route('organisations.switch', $secondOrganisation));

    $response->assertRedirect(route('dashboard', $secondOrganisation));
    $this
        ->actingAs($user->fresh())
        ->get(route('dashboard', $secondOrganisation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentOrganisation.id', $secondOrganisation->id)
            ->where('currentOrganisation.role', OrganisationRole::ExecutiveViewer->value)
            ->where('currentOrganisation.programIds', [$secondProgram->id])
            ->where('currentOrganisation.isOwner', false)
        );
});

it('forbids access to a organisation that the user has not joined', function () {
    $user = User::factory()->create();
    $joinedOrganisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();

    $joinedOrganisation->memberships()->create([
        'user_id' => $user->id,
        'role' => OrganisationRole::OrganisationAdministrator,
    ]);
    $user->switchOrganisation($joinedOrganisation);

    $this
        ->actingAs($user)
        ->get(route('organisations.edit', $otherOrganisation))
        ->assertForbidden();
});

it('clears current organisation context when leaving the only organisation', function () {
    $user = User::factory()->create();
    $organisation = $user->currentOrganisation;
    $user->organisationMembership($organisation)->update([
        'is_owner' => false,
        'role' => OrganisationRole::CaseWorker,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('organisations.leave', $organisation));

    $response->assertRedirect(route('organisations.index'));
    expect($user->fresh()->current_organisation_id)->toBeNull()
        ->and($user->fresh()->belongsToOrganisation($organisation))->toBeFalse();
});

it('clears current organisation context when deleting the only organisation', function () {
    $user = User::factory()->create();
    $organisation = $user->currentOrganisation;

    $response = $this
        ->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete(route('organisations.destroy', $organisation), [
            'name' => $organisation->name,
        ]);

    $response->assertRedirect(route('organisations.index'));
    expect($user->fresh()->current_organisation_id)->toBeNull();
});
