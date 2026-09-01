<?php

use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Billing\BillingAccountController;
use App\Http\Controllers\Billing\BillingContactController;
use App\Http\Controllers\Billing\BillingInvitationController;
use App\Http\Controllers\Billing\BillingMembershipController;
use App\Http\Controllers\Organisations\MembershipHoldController;
use App\Http\Controllers\Organisations\OrganisationController;
use App\Http\Controllers\Organisations\OrganisationInvitationController;
use App\Http\Controllers\Organisations\OrganisationLifecycleController;
use App\Http\Controllers\Organisations\OrganisationMemberController;
use App\Http\Controllers\Organisations\OrganisationOwnershipTransferController;
use App\Http\Controllers\Organisations\OrganisationSlugController;
use App\Http\Controllers\Organisations\ProgramController;
use App\Http\Controllers\Settings\OtherBrowserSessionController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RecoveryCodeAcknowledgementController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\EnsureOrganisationAccess;
use App\Http\Middleware\EnsureOrganisationMembership;
use App\Http\Middleware\EnsureRecentMfa;
use App\Http\Middleware\EnsureRecentPassword;
use App\Http\Middleware\EnsureStaffSecurityRequirements;
use App\Http\Middleware\UseOrganisationContext;
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
        Route::get('settings/billing-accounts', [BillingAccountController::class, 'index'])->name('billing-accounts.index');
        Route::post('settings/billing-accounts', [BillingAccountController::class, 'store'])->name('billing-accounts.store');
        Route::get('settings/billing-accounts/{billing_account}', [BillingAccountController::class, 'show'])->name('billing-accounts.show');
        Route::delete('settings/billing-accounts/{billing_account}', [BillingAccountController::class, 'destroy'])->name('billing-accounts.destroy');
        Route::post('settings/billing-accounts/{billing_account}/invitations', [BillingInvitationController::class, 'store'])->name('billing-accounts.invitations.store');
        Route::post('settings/billing-accounts/{billing_account}/contacts', [BillingContactController::class, 'store'])->name('billing-accounts.contacts.store');
        Route::delete('settings/billing-accounts/{billing_account}/contacts/{billing_contact}', [BillingContactController::class, 'destroy'])->name('billing-accounts.contacts.destroy');
        Route::patch('settings/billing-accounts/{billing_account}/memberships/{billing_membership}', [BillingMembershipController::class, 'update'])->name('billing-accounts.memberships.update');
        Route::delete('settings/billing-accounts/{billing_account}/memberships/{billing_membership}', [BillingMembershipController::class, 'destroy'])->name('billing-accounts.memberships.destroy');
        Route::get('billing-invitations/{token}', [BillingInvitationController::class, 'show'])->name('billing-invitations.show');
        Route::post('billing-invitations/{token}/accept', [BillingInvitationController::class, 'accept'])->name('billing-invitations.accept');

        Route::get('settings/organisations', [OrganisationController::class, 'index'])->name('organisations.index');
        Route::post('settings/organisations', [OrganisationController::class, 'store'])->name('organisations.store');

        Route::delete('settings/security/other-browser-sessions', OtherBrowserSessionController::class)
            ->middleware([EnsureRecentPassword::class, EnsureRecentMfa::class])
            ->name('security.other-browser-sessions.destroy');

        Route::middleware([EnsureOrganisationMembership::class, UseOrganisationContext::class])->group(function () {
            Route::get('settings/organisations/{organisation}', [OrganisationController::class, 'edit'])->middleware(EnsureOrganisationAccess::class.':recovery')->name('organisations.edit');
            Route::patch('settings/organisations/{organisation}', [OrganisationController::class, 'update'])->middleware([EnsureOrganisationAccess::class.':administration', EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.update');
            Route::delete('settings/organisations/{organisation}', [OrganisationController::class, 'destroy'])->middleware([EnsureOrganisationAccess::class.':recovery', EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.destroy');
            Route::post('settings/organisations/{organisation}/switch', [OrganisationController::class, 'switch'])->middleware(EnsureOrganisationAccess::class.':full')->name('organisations.switch');
            Route::delete('settings/organisations/{organisation}/leave', [OrganisationController::class, 'leave'])->name('organisations.leave');

            Route::patch('settings/organisations/{organisation}/lifecycle', [OrganisationLifecycleController::class, 'update'])->middleware([EnsureOrganisationAccess::class.':recovery', EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.lifecycle.update');
            Route::patch('settings/organisations/{organisation}/slug', [OrganisationSlugController::class, 'update'])->middleware([EnsureOrganisationAccess::class.':administration', EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.slug.update');
            Route::post('settings/organisations/{organisation}/ownership-transfers', [OrganisationOwnershipTransferController::class, 'store'])->middleware([EnsureOrganisationAccess::class.':administration', EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.ownership-transfers.store');
            Route::patch('settings/organisations/{organisation}/ownership-transfers/{transfer}', [OrganisationOwnershipTransferController::class, 'update'])->middleware([EnsureOrganisationAccess::class.':membership', EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.ownership-transfers.update');

            Route::patch('settings/organisations/{organisation}/members/{user}', [OrganisationMemberController::class, 'update'])->middleware([EnsureOrganisationAccess::class.':administration', EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.members.update');
            Route::delete('settings/organisations/{organisation}/members/{user}', [OrganisationMemberController::class, 'destroy'])->middleware([EnsureOrganisationAccess::class.':administration', EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.members.destroy');
            Route::post('settings/organisations/{organisation}/members/{user}/holds', [MembershipHoldController::class, 'store'])->middleware([EnsureOrganisationAccess::class.':administration', EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.members.holds.store');
            Route::delete('settings/organisations/{organisation}/members/{user}/holds/{hold}', [MembershipHoldController::class, 'destroy'])->middleware([EnsureOrganisationAccess::class.':administration', EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.members.holds.destroy');

            Route::post('settings/organisations/{organisation}/invitations', [OrganisationInvitationController::class, 'store'])->middleware([EnsureOrganisationAccess::class.':administration', EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.invitations.store');
            Route::delete('settings/organisations/{organisation}/invitations/{invitation}', [OrganisationInvitationController::class, 'destroy'])->middleware([EnsureOrganisationAccess::class.':administration', EnsureRecentPassword::class, EnsureRecentMfa::class])->name('organisations.invitations.destroy');

            Route::get('settings/organisations/{organisation}/programs/search', [ProgramController::class, 'search'])->middleware(EnsureOrganisationAccess::class)->name('organisations.programs.search');
            Route::get('settings/organisations/{organisation}/programs/report', [ProgramController::class, 'report'])->middleware(EnsureOrganisationAccess::class)->name('organisations.programs.report');
            Route::get('settings/organisations/{organisation}/programs/export', [ProgramController::class, 'export'])->middleware(EnsureOrganisationAccess::class)->name('organisations.programs.export');
            Route::get('settings/organisations/{organisation}/programs/{program}', [ProgramController::class, 'show'])->middleware(EnsureOrganisationAccess::class)->name('organisations.programs.show');
            Route::patch('settings/organisations/{organisation}/programs/{program}', [ProgramController::class, 'update'])->middleware(EnsureOrganisationAccess::class.':administration')->name('organisations.programs.update');
        });
    });
});
