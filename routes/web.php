<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Organisations\OrganisationInvitationController;
use App\Http\Middleware\EnsureOrganisationAccess;
use App\Http\Middleware\EnsureOrganisationMembership;
use App\Http\Middleware\EnsureStaffSecurityRequirements;
use App\Http\Middleware\UseOrganisationContext;
use App\Models\Organisation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::model('current_organisation', Organisation::class);

Route::get('/source-and-licence', fn (): View => view('source-and-licence', [
    'repository' => config('source.repository'),
    'release' => config('source.release'),
]))->name('source-and-licence');

Route::get('/source-and-licence/license', fn (): Response => response(
    File::get(base_path('LICENSE')),
    headers: ['Content-Type' => 'text/plain; charset=UTF-8'],
))->name('source-and-licence.license');

Route::prefix('{current_organisation}')
    ->middleware(['auth', 'verified', EnsureStaffSecurityRequirements::class, EnsureOrganisationMembership::class, UseOrganisationContext::class, EnsureOrganisationAccess::class.':full'])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('invitations/{invitation}/accept', [OrganisationInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [OrganisationInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
