<?php

use App\Enums\TeamRole;
use App\Enums\TeamStatus;
use App\Models\Program;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('creates a pending organisation team with a separate owner membership', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('teams.store'), ['name' => 'Harbour Kind']);

    $team = Team::query()->where('slug', 'harbour-kind')->firstOrFail();

    $response->assertRedirect(route('teams.edit', $team));
    expect($user->fresh()->current_team_id)->toBe($team->id);
    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Harbour Kind',
        'slug' => 'harbour-kind',
        'status' => TeamStatus::Pending->value,
    ]);
    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $user->id,
        'is_owner' => true,
        'role' => null,
    ]);
});

it('does not create a personal team for a new user', function () {
    $user = User::factory()->create();

    expect($user->teams()->where('is_personal', true)->count())->toBe(0);
    $this->assertDatabaseMissing('teams', ['is_personal' => true]);
});

it('assigns one operational role and program access independently of ownership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $housing = Program::factory()->for($team)->create(['name' => 'Housing']);
    $food = Program::factory()->for($team)->create(['name' => 'Food']);

    $team->memberships()->create([
        'user_id' => $owner->id,
        'is_owner' => true,
        'role' => null,
    ]);
    $team->memberships()->create([
        'user_id' => $member->id,
        'role' => TeamRole::CaseWorker,
    ]);

    $response = $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::ProgramManager->value,
            'program_ids' => [$housing->id],
        ]);

    $response->assertRedirect(route('teams.edit', $team));
    expect($owner->fresh()->ownsTeam($team))->toBeTrue()
        ->and($owner->teamRole($team))->toBeNull()
        ->and($owner->hasProgramAccess($housing))->toBeFalse()
        ->and($member->fresh()->teamRole($team))->toBe(TeamRole::ProgramManager)
        ->and($member->hasProgramAccess($housing))->toBeTrue()
        ->and($member->hasProgramAccess($food))->toBeFalse();
});

it('lets team administrators manage member roles without granting ownership', function () {
    $administrator = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->memberships()->create([
        'user_id' => $administrator->id,
        'role' => TeamRole::TeamAdministrator,
    ]);
    $team->memberships()->create([
        'user_id' => $member->id,
        'role' => TeamRole::CaseWorker,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::ExecutiveViewer->value,
        ]);

    $response->assertRedirect(route('teams.edit', $team));
    expect($administrator->ownsTeam($team))->toBeFalse()
        ->and($member->fresh()->teamRole($team))->toBe(TeamRole::ExecutiveViewer);
});

it('rejects program access from another team without changing the membership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $otherProgram = Program::factory()->for($otherTeam)->create();

    $team->memberships()->create([
        'user_id' => $owner->id,
        'is_owner' => true,
    ]);
    $membership = $team->memberships()->create([
        'user_id' => $member->id,
        'role' => TeamRole::CaseWorker,
    ]);

    $response = $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->from(route('teams.edit', $team))
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::ProgramManager->value,
            'program_ids' => [$otherProgram->id],
        ]);

    $response
        ->assertRedirect(route('teams.edit', $team))
        ->assertSessionHasErrors([
            'program_ids.0' => 'The selected program is not available for this team.',
        ]);
    expect($membership->fresh()->role)->toBe(TeamRole::CaseWorker)
        ->and($membership->programs()->count())->toBe(0);
});

it('switches to a fresh destination dashboard and recomputes team context', function () {
    $user = User::factory()->create();
    $firstTeam = Team::factory()->create(['name' => 'HarbourKind']);
    $secondTeam = Team::factory()->create(['name' => 'NeighbourLink']);
    $firstProgram = Program::factory()->for($firstTeam)->create();
    $secondProgram = Program::factory()->for($secondTeam)->create();

    $firstMembership = $firstTeam->memberships()->create([
        'user_id' => $user->id,
        'role' => TeamRole::ProgramManager,
    ]);
    $firstMembership->programs()->attach($firstProgram);
    $secondMembership = $secondTeam->memberships()->create([
        'user_id' => $user->id,
        'role' => TeamRole::ExecutiveViewer,
    ]);
    $secondMembership->programs()->attach($secondProgram);
    $user->switchTeam($firstTeam);

    $response = $this
        ->actingAs($user)
        ->post(route('teams.switch', $secondTeam));

    $response->assertRedirect(route('dashboard', $secondTeam));
    $this
        ->actingAs($user->fresh())
        ->get(route('dashboard', $secondTeam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentTeam.id', $secondTeam->id)
            ->where('currentTeam.role', TeamRole::ExecutiveViewer->value)
            ->where('currentTeam.programIds', [$secondProgram->id])
            ->where('currentTeam.isOwner', false)
        );
});

it('forbids access to a team that the user has not joined', function () {
    $user = User::factory()->create();
    $joinedTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();

    $joinedTeam->memberships()->create([
        'user_id' => $user->id,
        'role' => TeamRole::TeamAdministrator,
    ]);
    $user->switchTeam($joinedTeam);

    $this
        ->actingAs($user)
        ->get(route('teams.edit', $otherTeam))
        ->assertForbidden();
});
