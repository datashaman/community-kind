<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Http\Middleware\EnsureRecentMfa;
use App\Http\Middleware\EnsureRecentPassword;
use App\Http\Middleware\EnsureStaffSecurityRequirements;
use App\Http\Middleware\ProtectSensitiveFortifyRoutes;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureStaffSecurityRequirements::class,
            EnsureRecentMfa::class,
            EnsureRecentPassword::class,
            ProtectSensitiveFortifyRoutes::class,
        ]);
    }

    public function test_team_member_roles_can_be_updated_by_owners()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);
        $team->members()->attach($member, ['role' => TeamRole::CaseWorker->value]);

        $response = $this
            ->actingAs($owner)
            ->patch(route('teams.members.update', [$team, $member]), [
                'role' => TeamRole::TeamAdministrator->value,
            ]);

        $response->assertRedirect(route('teams.edit', $team));

        $this->assertEquals(
            TeamRole::TeamAdministrator->value,
            $team->members()->where('user_id', $member->id)->first()->pivot->role->value,
        );
    }

    public function test_team_member_roles_cannot_be_updated_by_program_managers()
    {
        $owner = User::factory()->create();
        $programManager = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);
        $team->members()->attach($programManager, ['role' => TeamRole::ProgramManager->value]);
        $team->members()->attach($member, ['role' => TeamRole::CaseWorker->value]);

        $response = $this
            ->actingAs($programManager)
            ->patch(route('teams.members.update', [$team, $member]), [
                'role' => TeamRole::TeamAdministrator->value,
            ]);

        $response->assertForbidden();
    }

    public function test_team_members_can_be_removed_by_owners()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);
        $team->members()->attach($member, ['role' => TeamRole::CaseWorker->value]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $member]));

        $response->assertRedirect(route('teams.edit', $team));

        $this->assertFalse($member->fresh()->belongsToTeam($team));
    }

    public function test_team_members_cannot_be_removed_by_program_managers()
    {
        $owner = User::factory()->create();
        $programManager = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);
        $team->members()->attach($programManager, ['role' => TeamRole::ProgramManager->value]);
        $team->members()->attach($member, ['role' => TeamRole::CaseWorker->value]);

        $response = $this
            ->actingAs($programManager)
            ->delete(route('teams.members.destroy', [$team, $member]));

        $response->assertForbidden();
    }

    public function test_team_owner_cannot_be_removed()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $owner]));

        $response->assertForbidden();

        $this->assertTrue($owner->fresh()->belongsToTeam($team));
    }

    public function test_team_member_role_cannot_be_set_to_owner()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);
        $team->members()->attach($member, ['role' => TeamRole::CaseWorker->value]);

        $response = $this
            ->actingAs($owner)
            ->patch(route('teams.members.update', [$team, $member]), [
                'role' => 'owner',
            ]);

        $response->assertSessionHasErrors('role');

        $this->assertEquals(
            TeamRole::CaseWorker->value,
            $team->members()->where('user_id', $member->id)->first()->pivot->role->value,
        );
    }

    public function test_removed_member_current_team_is_set_to_their_remaining_team()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $remainingTeam = $member->currentTeam;
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);
        $team->members()->attach($member, ['role' => TeamRole::CaseWorker->value]);

        $member->update(['current_team_id' => $team->id]);

        $this
            ->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $member]));

        $this->assertEquals($remainingTeam->id, $member->fresh()->current_team_id);
    }
}
