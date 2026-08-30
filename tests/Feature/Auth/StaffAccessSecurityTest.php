<?php

use App\Actions\Security\RecordPlatformSecurityEvent;
use App\Actions\Security\RevokeOtherBrowserSessions;
use App\Actions\Teams\IssueTeamInvitation;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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
        ->assertSessionHas('auth.mfa_confirmed_at');

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

test('revoking other database sessions is audited', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create();
    DB::table('sessions')->insert([
        [
            'id' => 'current-session',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => time(),
        ],
        [
            'id' => 'other-session',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => time(),
        ],
    ]);

    $revoked = app(RevokeOtherBrowserSessions::class)->handle($user, 'current-session');
    app(RecordPlatformSecurityEvent::class)->handle(
        'other_browser_sessions_revoked',
        $user,
        $user,
        ['revoked_count' => $revoked],
    );

    expect($revoked)->toBe(1);
    $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    $this->assertDatabaseHas('platform_security_events', [
        'type' => 'other_browser_sessions_revoked',
        'actor_user_id' => $user->id,
        'subject_user_id' => $user->id,
    ]);
});
