<?php

use App\Actions\Security\RecordPlatformSecurityEvent;
use App\Actions\Teams\IssueTeamInvitation;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

test('issued invitations store only a hash and expire after 72 hours', function () {
    $inviter = User::factory()->create();
    $team = Team::factory()->create();

    $issued = app(IssueTeamInvitation::class)->handle(
        $team,
        $inviter,
        'INVITED@example.com',
        TeamRole::Member,
    );

    expect($issued->invitation->token_hash)
        ->toBe(hash('sha256', $issued->token))
        ->not->toBe($issued->token)
        ->and($issued->invitation->email)->toBe('invited@example.com')
        ->and(abs($issued->invitation->expires_at->diffInHours(now())))->toBeGreaterThan(71.9);
});

test('operational routes require confirmed MFA and recovery-code acknowledgement', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('security.edit', ['required' => 'mfa']));

    $user->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($user->refresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('security.edit', ['required' => 'recovery-codes']));
});

test('staff who completed security onboarding can use operational routes', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('sensitive team actions require a recent MFA confirmation', function () {
    Notification::fake();

    $owner = User::factory()->withTwoFactor()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('teams.invitations.store', $team), [
            'email' => 'invited@example.com',
            'role' => TeamRole::Member->value,
        ])
        ->assertRedirect(route('mfa.confirm'));

    Notification::assertNothingSent();
});

test('staff can acknowledge recovery codes only after enabling MFA', function () {
    $user = User::factory()->withTwoFactor()->create([
        'recovery_codes_acknowledged_at' => null,
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('security.recovery-codes.acknowledge'), [
            'acknowledged' => true,
        ])
        ->assertRedirect(route('security.edit'))
        ->assertSessionMissing('auth.mfa_confirmed_at');

    expect($user->refresh()->hasAcknowledgedRecoveryCodes())->toBeTrue();
});

test('regenerating recovery codes requires a new acknowledgement', function () {
    $user = User::factory()->withTwoFactor()->create();

    $user->forceFill([
        'two_factor_recovery_codes' => encrypt(json_encode(['replacement-code'])),
    ])->save();

    expect($user->refresh()->hasAcknowledgedRecoveryCodes())->toBeFalse();
});

test('absolute session lifetime signs staff out', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.session_started_at' => now()->subHours(13)->getTimestamp()])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('remember me is ignored for staff logins', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $response->assertCookieMissing(Auth::guard('web')->getRecallerName());
});

test('password changes require a recent MFA confirmation', function () {
    $user = User::factory()->withTwoFactor()->create();

    $response = $this
        ->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ]);

    $response->assertRedirect(route('mfa.confirm'));
    expect(Auth::guard('web')->validate([
        'email' => $user->email,
        'password' => 'password',
    ]))->toBeTrue();
});

test('profile deletion requires a recent MFA confirmation', function () {
    $user = User::factory()->withTwoFactor()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password']);

    $response->assertRedirect(route('mfa.confirm'));
    $this->assertModelExists($user);
});

test('disabling MFA requires a recent MFA confirmation', function () {
    $user = User::factory()->withTwoFactor()->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('two-factor.disable'));

    $response->assertRedirect(route('mfa.confirm'));
    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

test('regenerating recovery codes requires a recent MFA confirmation', function () {
    $user = User::factory()->withTwoFactor()->create();
    $recoveryCodes = $user->two_factor_recovery_codes;

    $response = $this
        ->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.regenerate-recovery-codes'));

    $response->assertRedirect(route('mfa.confirm'));
    expect($user->refresh()->two_factor_recovery_codes)->toBe($recoveryCodes);
});

test('password confirmation after a mutation returns to its safe form page', function () {
    Notification::fake();

    $owner = User::factory()->withTwoFactor()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $formUrl = route('teams.edit', $team);

    $this->actingAs($owner)
        ->from($formUrl)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'invited@example.com',
            'role' => TeamRole::Member->value,
        ])
        ->assertRedirect(route('password.confirm'));

    $this->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertRedirect($formUrl);
    Notification::assertNothingSent();
});

test('MFA confirmation after a mutation returns to its safe form page', function () {
    Notification::fake();

    $owner = User::factory()->withTwoFactor()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $formUrl = route('teams.edit', $team);

    $this->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from($formUrl)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'invited@example.com',
            'role' => TeamRole::Member->value,
        ])
        ->assertRedirect(route('mfa.confirm'));

    $this->mock(TwoFactorAuthenticationProvider::class, function ($mock): void {
        $mock->shouldReceive('verify')
            ->once()
            ->with('secret', '123456')
            ->andReturnTrue();
    });

    $this->post(route('mfa.confirm.store'), ['code' => '123456'])
        ->assertRedirect($formUrl);
    Notification::assertNothingSent();
});

test('revoking other database sessions is audited', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->withTwoFactor()->create();
    DB::table('sessions')->insert([
        'id' => 'other-session',
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => time(),
    ]);

    $response = $this
        ->actingAs($user)
        ->withSession([
            'auth.password_confirmed_at' => time(),
            'auth.mfa_confirmed_at' => time(),
        ])
        ->delete(route('security.other-browser-sessions.destroy'));

    $response->assertRedirect();
    $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    $this->assertDatabaseHas('platform_security_events', [
        'type' => 'other_browser_sessions_revoked',
        'actor_user_id' => $user->id,
        'subject_user_id' => $user->id,
    ]);
});

test('session revocation rolls back when its audit cannot be recorded', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->withTwoFactor()->create();
    DB::table('sessions')->insert([
        'id' => 'other-session',
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => time(),
    ]);
    $this->mock(RecordPlatformSecurityEvent::class, function ($mock): void {
        $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable.'));
    });

    $response = $this
        ->actingAs($user)
        ->withSession([
            'auth.password_confirmed_at' => time(),
            'auth.mfa_confirmed_at' => time(),
        ])
        ->delete(route('security.other-browser-sessions.destroy'));

    $response->assertServerError();
    $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
    $this->assertDatabaseMissing('platform_security_events', [
        'type' => 'other_browser_sessions_revoked',
        'subject_user_id' => $user->id,
    ]);
});
