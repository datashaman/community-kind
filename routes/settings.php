<?php

use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Organisations\OrganisationController;
use App\Http\Controllers\Organisations\OrganisationInvitationController;
use App\Http\Controllers\Organisations\OrganisationMemberController;
use App\Http\Controllers\Settings\OtherBrowserSessionController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RecoveryCodeAcknowledgementController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\EnsureOrganisationMembership;
use App\Http\Middleware\EnsureRecentMfa;
use App\Http\Middleware\EnsureRecentPassword;
use App\Http\Middleware\EnsureStaffSecurityRequirements;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])
        ->middleware([EnsureStaffSecurityRequirements::class, EnsureRecentMfa::class])
        ->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(EnsureRecentPassword::class)
        ->name('security.edit');

    Route::post('settings/security/recovery-codes/acknowledge', RecoveryCodeAcknowledgementController::class)
        ->middleware(EnsureRecentPassword::class)
        ->name('security.recovery-codes.acknowledge');

    Route::get('auth/mfa-confirmation', [MfaChallengeController::class, 'create'])
        ->middleware(EnsureStaffSecurityRequirements::class)
        ->name('mfa.confirm');

    Route::post('auth/mfa-confirmation', [MfaChallengeController::class, 'store'])
        ->middleware(['throttle:6,1', EnsureStaffSecurityRequirements::class])
        ->name('mfa.confirm.store');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware([EnsureStaffSecurityRequirements::class, EnsureRecentMfa::class, 'throttle:6,1'])
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::middleware(EnsureStaffSecurityRequirements::class)->group(function () {
        Route::get('settings/organisations', [OrganisationController::class, 'index'])->name('organisations.index');
        Route::post('settings/organisations', [OrganisationController::class, 'store'])->name('organisations.store');

        Route::delete('settings/security/other-browser-sessions', OtherBrowserSessionController::class)
            ->middleware([EnsureRecentPassword::class, EnsureRecentMfa::class])
            ->name('security.other-browser-sessions.destroy');

        Route::middleware(EnsureOrganisationMembership::class)->group(function () {
            Route::get('settings/organisations/{organisation}', [OrganisationController::class, 'edit'])->name('organisations.edit');
            Route::patch('settings/organisations/{organisation}', [OrganisationController::class, 'update'])->middleware([EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.update');
            Route::delete('settings/organisations/{organisation}', [OrganisationController::class, 'destroy'])->middleware([EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.destroy');
            Route::post('settings/organisations/{organisation}/switch', [OrganisationController::class, 'switch'])->name('organisations.switch');
            Route::delete('settings/organisations/{organisation}/leave', [OrganisationController::class, 'leave'])->name('organisations.leave');

            Route::patch('settings/organisations/{organisation}/members/{user}', [OrganisationMemberController::class, 'update'])->middleware([EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.members.update');
            Route::delete('settings/organisations/{organisation}/members/{user}', [OrganisationMemberController::class, 'destroy'])->middleware([EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.members.destroy');

            Route::post('settings/organisations/{organisation}/invitations', [OrganisationInvitationController::class, 'store'])->middleware([EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.invitations.store');
            Route::delete('settings/organisations/{organisation}/invitations/{invitation}', [OrganisationInvitationController::class, 'destroy'])->middleware([EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.invitations.destroy');
        });
    });
});
