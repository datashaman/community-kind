<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Organisations\CaseAssignmentController;
use App\Http\Controllers\Organisations\CaseConfidentialityController;
use App\Http\Controllers\Organisations\CaseExportController;
use App\Http\Controllers\Organisations\CaseItemController;
use App\Http\Controllers\Organisations\CaseWorkflowController;
use App\Http\Controllers\Organisations\IntakeRequestController;
use App\Http\Controllers\Organisations\IntakeTransitionController;
use App\Http\Controllers\Organisations\OrganisationInvitationController;
use App\Http\Controllers\Organisations\PartyAddressController;
use App\Http\Controllers\Organisations\PartyConsentController;
use App\Http\Controllers\Organisations\PartyController;
use App\Http\Controllers\Organisations\PartyDuplicateReviewController;
use App\Http\Controllers\Organisations\PartyRelationshipController;
use App\Http\Controllers\Organisations\PartySafeContactInstructionController;
use App\Http\Controllers\Organisations\ProgramController;
use App\Http\Controllers\Organisations\ServiceCaseController;
use App\Http\Controllers\Public\OrganisationController as PublicOrganisationController;
use App\Http\Middleware\EnsureOrganisationAccess;
use App\Http\Middleware\EnsureOrganisationMembership;
use App\Http\Middleware\EnsureStaffSecurityRequirements;
use App\Http\Middleware\ResolvePublicOrganisation;
use App\Http\Middleware\UseOrganisationContext;
use App\Models\Organisation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

$securityText = function (Request $request): Response {
    $host = $request->getSchemeAndHttpHost();
    $contents = implode("\n", [
        'Contact: '.config('security.vulnerability_contact'),
        'Expires: '.config('security.security_txt_expires'),
        'Canonical: '.$host.'/.well-known/security.txt',
        'Policy: '.$host.'/security-policy',
        '',
    ]);

    return response($contents, headers: [
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Cache-Control' => 'public, max-age=3600',
        'X-Content-Type-Options' => 'nosniff',
    ]);
};
$securityPolicy = fn (): Response => response(
    File::get(base_path('SECURITY.md')),
    headers: [
        'Content-Type' => 'text/markdown; charset=UTF-8',
        'X-Content-Type-Options' => 'nosniff',
    ],
);

Route::domain('{public_organisation}.'.config('organisations.public_domain'))
    ->middleware(ResolvePublicOrganisation::class)
    ->group(function () use ($securityPolicy, $securityText): void {
        Route::get('/', PublicOrganisationController::class)->name('public.organisations.show');
        Route::get('/.well-known/security.txt', $securityText)->name('public.security.txt');
        Route::get('/security-policy', $securityPolicy)->name('public.security.policy');
    });

Route::inertia('/', 'welcome')->name('home');
Route::model('current_organisation', Organisation::class);

Route::get('/.well-known/security.txt', $securityText)->name('security.txt');

Route::get('/security-policy', $securityPolicy)->name('security.policy');

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
        Route::get('programs', [ProgramController::class, 'index'])->name('programs.index');
        Route::resource('parties', PartyController::class)->only(['index', 'store', 'show', 'update']);
        Route::resource('intakes', IntakeRequestController::class)
            ->parameters(['intakes' => 'intake'])
            ->only(['index', 'store', 'show']);
        Route::post('intakes/{intake}/transitions', [IntakeTransitionController::class, 'store'])->name('intakes.transitions.store');
        Route::post('intakes/{intake}/assignments', [CaseAssignmentController::class, 'store'])->name('intakes.assignments.store');
        Route::get('cases/{case}', [ServiceCaseController::class, 'show'])->name('cases.show');
        Route::post('cases/{case}/transitions', [ServiceCaseController::class, 'transition'])->name('cases.transitions.store');
        Route::post('cases/{case}/classification', [CaseConfidentialityController::class, 'reclassify'])->name('cases.classification.store');
        Route::post('cases/{case}/restricted-access-grants', [CaseConfidentialityController::class, 'grant'])->name('cases.restricted-access-grants.store');
        Route::delete('cases/{case}/restricted-access-grants/{grant}', [CaseConfidentialityController::class, 'revoke'])->name('cases.restricted-access-grants.destroy');
        Route::post('cases/{case}/risk-assessments', [CaseConfidentialityController::class, 'risk'])->name('cases.risk-assessments.store');
        Route::get('programs/{program}/cases/export', CaseExportController::class)->name('programs.cases.export');
        Route::post('cases/{case}/items', [CaseItemController::class, 'store'])->name('cases.items.store');
        Route::post('cases/{case}/items/{subjectType}/{subject}/transitions', [CaseWorkflowController::class, 'store'])->name('cases.items.transitions.store');
        Route::post('duplicate-reviews/{duplicate_review}', [PartyDuplicateReviewController::class, 'store'])->name('duplicate-reviews.store');
        Route::delete('duplicate-reviews/{duplicate_review}', [PartyDuplicateReviewController::class, 'destroy'])->name('duplicate-reviews.destroy');
        Route::post('parties/{party}/consents', [PartyConsentController::class, 'store'])->name('parties.consents.store');
        Route::post('parties/{party}/addresses', [PartyAddressController::class, 'store'])->name('parties.addresses.store');
        Route::post('parties/{party}/relationships', [PartyRelationshipController::class, 'store'])->name('parties.relationships.store');
        Route::post('parties/{party}/safe-contact-instructions', [PartySafeContactInstructionController::class, 'store'])->name('parties.safe-contact-instructions.store');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('invitations/{invitation}/accept', [OrganisationInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [OrganisationInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
