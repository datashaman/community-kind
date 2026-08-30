<?php

namespace Tests\Feature\Auth;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_screen_is_unavailable()
    {
        $response = $this->get(route('register'));

        $response->assertNotFound();
    }

    public function test_registration_screen_includes_team_invitation_context()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Laravel Team']);
        $team->members()->attach($owner, ['is_owner' => true]);

        $token = 'registration-screen-invitation-token';
        $invitation = TeamInvitation::factory()->forToken($token)->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this->get(route('register', ['invitation' => $token]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/register')
            ->where('teamInvitation.code', $token)
            ->where('teamInvitation.email', 'invited@example.com')
            ->where('teamInvitation.teamName', 'Laravel Team'),
        );
    }

    public function test_new_users_can_register()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['is_owner' => true]);
        $token = 'new-user-registration-token';
        TeamInvitation::factory()->forToken($token)->create([
            'team_id' => $team->id,
            'email' => 'test@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
            'invitation' => $token,
        ]);

        $this->assertAuthenticated();

        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_new_users_receive_an_email_verification_notification()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $token = 'notification-registration-token';
        TeamInvitation::factory()->forToken($token)->create([
            'team_id' => $team->id,
            'email' => 'test@example.com',
            'invited_by' => $owner->id,
        ]);

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
            'invitation' => $token,
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_rejects_an_email_other_than_the_invited_address(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $token = 'email-bound-registration-token';
        TeamInvitation::factory()->forToken($token)->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $this->post(route('register.store'), [
            'name' => 'Wrong User',
            'email' => 'other@example.com',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
            'invitation' => $token,
        ])->assertSessionHasErrors('invitation');

        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
    }
}
