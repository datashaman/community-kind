<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Http\Middleware\EnsureRecentMfa;
use App\Http\Middleware\EnsureRecentPassword;
use App\Http\Middleware\EnsureStaffSecurityRequirements;
use App\Http\Middleware\ProtectSensitiveFortifyRoutes;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamInvitationTest extends TestCase
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

    public function test_team_invitations_can_be_created()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'invited@example.com',
                'role' => TeamRole::CaseWorker->value,
            ]);

        $response->assertRedirect(route('teams.edit', $team));

        $this->assertDatabaseHas('team_invitations', [
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'role' => TeamRole::CaseWorker->value,
        ]);
    }

    public function test_invitation_email_for_existing_users_uses_login_route()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);

        $token = 'existing-user-invitation-token';
        $invitation = TeamInvitation::factory()->forToken($token)->create([
            'team_id' => $team->id,
            'email' => $invitedUser->email,
            'invited_by' => $owner->id,
        ]);

        $mail = (new TeamInvitationNotification($invitation, $token, true))->toMail($invitedUser);

        $this->assertSame(route('login', ['invitation' => $token]), $mail->actionUrl);
        $this->assertStringContainsString('dashboard', implode(' ', $mail->introLines));
    }

    public function test_invitation_email_for_unknown_users_uses_registration_route()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);

        $token = 'new-user-invitation-token';
        $invitation = TeamInvitation::factory()->forToken($token)->create([
            'team_id' => $team->id,
            'email' => 'unknown@example.com',
            'invited_by' => $owner->id,
        ]);

        $mail = (new TeamInvitationNotification($invitation, $token, false))->toMail((object) []);

        $this->assertSame(route('register', ['invitation' => $token]), $mail->actionUrl);
        $this->assertStringContainsString('create', strtolower(implode(' ', $mail->introLines)));
    }

    public function test_team_invitations_can_be_created_by_admins()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);
        $team->members()->attach($admin, ['role' => TeamRole::TeamAdministrator->value]);

        $response = $this
            ->actingAs($admin)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'invited@example.com',
                'role' => TeamRole::CaseWorker->value,
            ]);

        $response->assertRedirect(route('teams.edit', $team));
    }

    public function test_team_invitations_cannot_assign_the_owner_role()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);
        $team->members()->attach($admin, ['role' => TeamRole::TeamAdministrator->value]);

        $response = $this
            ->actingAs($admin)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'invited@example.com',
                'role' => 'owner',
            ]);

        $response->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('team_invitations', [
            'team_id' => $team->id,
            'email' => 'invited@example.com',
        ]);
        Notification::assertNothingSent();
    }

    public function test_existing_team_members_cannot_be_invited()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);
        $team->members()->attach($member, ['role' => TeamRole::CaseWorker->value]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'member@example.com',
                'role' => TeamRole::CaseWorker->value,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_duplicate_invitations_cannot_be_created()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['is_owner' => true]);

        TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'invited@example.com',
                'role' => TeamRole::CaseWorker->value,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_revoked_invitations_can_be_reissued()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['is_owner' => true]);
        TeamInvitation::factory()->revoked()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'invited@example.com',
                'role' => TeamRole::CaseWorker->value,
            ]);

        $response->assertRedirect(route('teams.edit', $team));
        $this->assertDatabaseCount('team_invitations', 2);
        Notification::assertCount(1);
    }

    public function test_revoked_invitations_are_not_shown_as_pending()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['is_owner' => true]);
        TeamInvitation::factory()->revoked()->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('teams.edit', $team));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('teams/edit')
            ->has('invitations', 0),
        );
    }

    public function test_team_invitations_cannot_be_created_by_members()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);
        $team->members()->attach($member, ['role' => TeamRole::CaseWorker->value]);

        $response = $this
            ->actingAs($member)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'invited@example.com',
                'role' => TeamRole::CaseWorker->value,
            ]);

        $response->assertForbidden();
    }

    public function test_team_invitations_can_be_cancelled_by_owners()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('teams.invitations.destroy', [$team, $invitation]));

        $response->assertRedirect(route('teams.edit', $team));

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'revoked_by' => $owner->id,
        ]);
        $this->assertNotNull($invitation->fresh()->revoked_at);
    }

    public function test_team_invitations_can_be_accepted()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'role' => TeamRole::CaseWorker,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->post(route('invitations.accept', $invitation));

        $response->assertRedirect(route('dashboard'));
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Invitation accepted.']);

        $this->assertTrue($invitedUser->fresh()->belongsToTeam($team));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_team_invitations_can_be_declined_by_the_invited_user()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'revoked_by' => $invitedUser->id,
        ]);
        $this->assertNotNull($invitation->fresh()->revoked_at);
    }

    public function test_team_invitations_cannot_be_declined_by_uninvited_user()
    {
        $owner = User::factory()->create();
        $uninvitedUser = User::factory()->create(['email' => 'uninvited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($uninvitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_accepted_team_invitations_cannot_be_declined()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);

        $invitation = TeamInvitation::factory()->accepted()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_team_invitations_cannot_be_accepted_by_uninvited_user()
    {
        $owner = User::factory()->create();
        $uninvitedUser = User::factory()->create(['email' => 'uninvited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($uninvitedUser)
            ->post(route('invitations.accept', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertFalse($uninvitedUser->fresh()->belongsToTeam($team));
    }

    public function test_expired_invitations_cannot_be_accepted()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['is_owner' => true]);

        $invitation = TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->post(route('invitations.accept', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertFalse($invitedUser->fresh()->belongsToTeam($team));
    }
}
