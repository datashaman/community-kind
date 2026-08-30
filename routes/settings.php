<?php

use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Settings\OtherBrowserSessionController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RecoveryCodeAcknowledgementController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Teams\TeamMemberController;
use App\Http\Middleware\EnsureRecentMfa;
use App\Http\Middleware\EnsureStaffSecurityRequirements;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::post('settings/security/recovery-codes/acknowledge', RecoveryCodeAcknowledgementController::class)
        ->middleware(RequirePassword::class)
        ->name('security.recovery-codes.acknowledge');

    Route::get('auth/mfa-confirmation', [MfaChallengeController::class, 'create'])
        ->middleware(EnsureStaffSecurityRequirements::class)
        ->name('mfa.confirm');

    Route::post('auth/mfa-confirmation', [MfaChallengeController::class, 'store'])
        ->middleware(['throttle:6,1', EnsureStaffSecurityRequirements::class])
        ->name('mfa.confirm.store');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::middleware(EnsureStaffSecurityRequirements::class)->group(function () {
        Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

        Route::get('settings/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::post('settings/teams', [TeamController::class, 'store'])->name('teams.store');

        Route::delete('settings/security/other-browser-sessions', OtherBrowserSessionController::class)
            ->middleware([RequirePassword::class, EnsureRecentMfa::class])
            ->name('security.other-browser-sessions.destroy');

        Route::middleware(EnsureTeamMembership::class)->group(function () {
            Route::get('settings/teams/{team}', [TeamController::class, 'edit'])->name('teams.edit');
            Route::patch('settings/teams/{team}', [TeamController::class, 'update'])->middleware([RequirePassword::class, EnsureRecentMfa::class])->name('teams.update');
            Route::delete('settings/teams/{team}', [TeamController::class, 'destroy'])->middleware([RequirePassword::class, EnsureRecentMfa::class])->name('teams.destroy');
            Route::post('settings/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');
            Route::delete('settings/teams/{team}/leave', [TeamController::class, 'leave'])->name('teams.leave');

            Route::patch('settings/teams/{team}/members/{user}', [TeamMemberController::class, 'update'])->middleware([RequirePassword::class, EnsureRecentMfa::class])->name('teams.members.update');
            Route::delete('settings/teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->middleware([RequirePassword::class, EnsureRecentMfa::class])->name('teams.members.destroy');

            Route::post('settings/teams/{team}/invitations', [TeamInvitationController::class, 'store'])->middleware([RequirePassword::class, EnsureRecentMfa::class])->name('teams.invitations.store');
            Route::delete('settings/teams/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->middleware([RequirePassword::class, EnsureRecentMfa::class])->name('teams.invitations.destroy');
        });
    });
});
