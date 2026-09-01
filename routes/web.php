<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Demo\SandboxBootstrapController;
use App\Http\Controllers\Demo\SandboxController;
use App\Http\Controllers\Demo\SandboxOrganisationController;
use App\Http\Controllers\Demo\SandboxPersonaController;
use App\Http\Controllers\Organisations\AudienceSegmentController;
use App\Http\Controllers\Organisations\CaseAssignmentController;
use App\Http\Controllers\Organisations\CaseConfidentialityController;
use App\Http\Controllers\Organisations\CaseDocumentController;
use App\Http\Controllers\Organisations\CaseExportController;
use App\Http\Controllers\Organisations\CaseItemController;
use App\Http\Controllers\Organisations\CaseWorkflowController;
use App\Http\Controllers\Organisations\CommunityEngagementController;
use App\Http\Controllers\Organisations\DonationController;
use App\Http\Controllers\Organisations\ImpactChartExportController;
use App\Http\Controllers\Organisations\ImpactReportExportController;
use App\Http\Controllers\Organisations\IntakeRequestController;
use App\Http\Controllers\Organisations\IntakeTransitionController;
use App\Http\Controllers\Organisations\MessageTemplateController;
use App\Http\Controllers\Organisations\OrganisationConfigurationController;
use App\Http\Controllers\Organisations\OrganisationInvitationController;
use App\Http\Controllers\Organisations\PartyAddressController;
use App\Http\Controllers\Organisations\PartyConsentController;
use App\Http\Controllers\Organisations\PartyController;
use App\Http\Controllers\Organisations\PartyDuplicateReviewController;
use App\Http\Controllers\Organisations\PartyRelationshipController;
use App\Http\Controllers\Organisations\PartySafeContactInstructionController;
use App\Http\Controllers\Organisations\PortalAccessGrantController as OrganisationPortalAccessGrantController;
use App\Http\Controllers\Organisations\ProgramController;
use App\Http\Controllers\Organisations\PublishedImpactSnapshotController;
use App\Http\Controllers\Organisations\ServiceCaseController;
use App\Http\Controllers\Organisations\ServiceOperationsExportController;
use App\Http\Controllers\Organisations\SupporterJourneyController;
use App\Http\Controllers\Organisations\SupporterJourneyPolicyController;
use App\Http\Controllers\Organisations\TenantAuditEventController;
use App\Http\Controllers\Organisations\VolunteerOpportunityController;
use App\Http\Controllers\Portal\PortalAccessController;
use App\Http\Controllers\Portal\PortalConsentPreferenceController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PortalProfileController;
use App\Http\Controllers\Portal\PortalRecurringMandateController;
use App\Http\Controllers\Portal\PortalRegistrationController;
use App\Http\Controllers\Public\CommunityEventController as PublicCommunityEventController;
use App\Http\Controllers\Public\DonationController as PublicDonationController;
use App\Http\Controllers\Public\ImpactController as PublicImpactController;
use App\Http\Controllers\Public\InKindOfferController as PublicInKindOfferController;
use App\Http\Controllers\Public\OrganisationController as PublicOrganisationController;
use App\Http\Controllers\Public\VolunteerOpportunityController as PublicVolunteerOpportunityController;
use App\Http\Middleware\EnsureOrganisationAccess;
use App\Http\Middleware\EnsureOrganisationMembership;
use App\Http\Middleware\EnsurePortalAccess;
use App\Http\Middleware\EnsureRecentPassword;
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
        Route::get('/donate', [PublicDonationController::class, 'create'])->name('public.donations.create');
        Route::post('/donate', [PublicDonationController::class, 'store'])->middleware('throttle:10,1')->name('public.donations.store');
        Route::get('/volunteer', [PublicVolunteerOpportunityController::class, 'index'])->name('public.volunteers.index');
        Route::get('/volunteer/{opportunity}', [PublicVolunteerOpportunityController::class, 'show'])->name('public.volunteers.show');
        Route::post('/volunteer/{opportunity}', [PublicVolunteerOpportunityController::class, 'store'])->middleware('throttle:10,1')->name('public.volunteers.store');
        Route::get('/events', [PublicCommunityEventController::class, 'index'])->name('public.events.index');
        Route::get('/events/{event}', [PublicCommunityEventController::class, 'show'])->name('public.events.show');
        Route::post('/events/{event}', [PublicCommunityEventController::class, 'store'])->middleware('throttle:10,1')->name('public.events.store');
        Route::get('/in-kind', [PublicInKindOfferController::class, 'create'])->name('public.in-kind.create');
        Route::post('/in-kind', [PublicInKindOfferController::class, 'store'])->middleware('throttle:10,1')->name('public.in-kind.store');
        Route::get('/impact', PublicImpactController::class)->name('public.impact.show');
        Route::get('/portal/access/{token}', [PortalAccessController::class, 'use'])
            ->middleware('throttle:20,1')
            ->name('portal.access.use');
        Route::middleware(EnsurePortalAccess::class)->group(function (): void {
            Route::get('/portal', PortalController::class)->name('portal.show');
            Route::patch('/portal/profile', [PortalProfileController::class, 'update'])->name('portal.profile.update');
            Route::put('/portal/consent-preferences', [PortalConsentPreferenceController::class, 'update'])->name('portal.consent-preferences.update');
            Route::delete('/portal/recurring-mandates/{mandate}', [PortalRecurringMandateController::class, 'destroy'])->name('portal.recurring-mandates.destroy');
            Route::delete('/portal/registrations/{registration}', [PortalRegistrationController::class, 'destroy'])->name('portal.registrations.destroy');
            Route::delete('/portal/access', [PortalAccessController::class, 'destroy'])->name('portal.access.destroy');
        });
        Route::get('/.well-known/security.txt', $securityText)->name('public.security.txt');
        Route::get('/security-policy', $securityPolicy)->name('public.security.policy');
    });

Route::inertia('/', 'welcome')->name('home');
Route::model('current_organisation', Organisation::class);

Route::get('/demo', [SandboxController::class, 'create'])->name('demo.create');
Route::post('/demo', [SandboxController::class, 'store'])->middleware('throttle:3,60')->name('demo.store');
Route::delete('/demo', [SandboxController::class, 'destroy'])->middleware('throttle:3,60')->name('demo.destroy');
Route::get('/demo/bootstrap/{token}', [SandboxBootstrapController::class, 'show'])->middleware('throttle:30,1')->name('demo.bootstrap');
Route::post('/demo/bootstrap/{token}', [SandboxBootstrapController::class, 'store'])->middleware('throttle:10,1')->name('demo.bootstrap.store');
Route::get('/demo/personas', [SandboxPersonaController::class, 'index'])->name('demo.personas.index');
Route::post('/demo/personas', [SandboxPersonaController::class, 'store'])->middleware('throttle:30,1')->name('demo.personas.store');

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
        Route::get('dashboard/service-operations/export', ServiceOperationsExportController::class)->name('dashboard.service-operations.export');
        Route::get('dashboard/impact/export', ImpactReportExportController::class)->name('dashboard.impact.export');
        Route::get('dashboard/impact/chart.svg', ImpactChartExportController::class)->name('dashboard.impact.chart.export');
        Route::get('audit', TenantAuditEventController::class)->name('audit.index');
        Route::get('programs', [ProgramController::class, 'index'])->name('programs.index');
        Route::resource('donations', DonationController::class)->only(['index', 'show']);
        Route::resource('audience-segments', AudienceSegmentController::class)->only(['index', 'store', 'show']);
        Route::resource('supporter-journeys', SupporterJourneyController::class)->only(['index', 'store', 'show']);
        Route::resource('volunteers', VolunteerOpportunityController::class)->only(['index', 'store', 'show']);
        Route::get('community-engagement', [CommunityEngagementController::class, 'index'])->name('community-engagement.index');
        Route::post('community-engagement/events', [CommunityEngagementController::class, 'storeEvent'])->name('community-engagement.events.store');
        Route::post('community-engagement/event-registrations/{registration}/transitions', [CommunityEngagementController::class, 'transitionRegistration'])->name('community-engagement.event-registrations.transitions.store');
        Route::post('community-engagement/event-registrations/{registration}/reminder', [CommunityEngagementController::class, 'remindRegistration'])->name('community-engagement.event-registrations.reminder.store');
        Route::post('community-engagement/in-kind-offers/{offer}/transitions', [CommunityEngagementController::class, 'transitionOffer'])->name('community-engagement.in-kind-offers.transitions.store');
        Route::post('community-engagement/partners', [CommunityEngagementController::class, 'storePartner'])->name('community-engagement.partners.store');
        Route::post('community-engagement/partners/{partner}/commitments', [CommunityEngagementController::class, 'storeCommitment'])->name('community-engagement.partners.commitments.store');
        Route::get('organisation-configurations', [OrganisationConfigurationController::class, 'index'])->name('organisation-configurations.index');
        Route::post('organisation-configurations', [OrganisationConfigurationController::class, 'store'])->name('organisation-configurations.store');
        Route::post('organisation-configurations/{configuration}/activate', [OrganisationConfigurationController::class, 'activate'])->name('organisation-configurations.activate');
        Route::get('message-templates', [MessageTemplateController::class, 'index'])->name('message-templates.index');
        Route::post('message-templates', [MessageTemplateController::class, 'store'])->name('message-templates.store');
        Route::post('message-templates/{messageTemplate}/activate', [MessageTemplateController::class, 'activate'])->name('message-templates.activate');
        Route::get('supporter-journey-policy', [SupporterJourneyPolicyController::class, 'index'])->name('supporter-journey-policy.index');
        Route::post('supporter-journey-policy', [SupporterJourneyPolicyController::class, 'store'])->name('supporter-journey-policy.store');
        Route::post('supporter-journey-policy/{supporterJourneyPolicy}/activate', [SupporterJourneyPolicyController::class, 'activate'])->name('supporter-journey-policy.activate');
        Route::get('impact-snapshots', [PublishedImpactSnapshotController::class, 'index'])->name('impact-snapshots.index');
        Route::post('impact-snapshots', [PublishedImpactSnapshotController::class, 'store'])->name('impact-snapshots.store');
        Route::get('impact-snapshots/{snapshot}/download', [PublishedImpactSnapshotController::class, 'download'])->name('impact-snapshots.download');
        Route::post('volunteers/{volunteer}/applications/{application}/transitions', [VolunteerOpportunityController::class, 'transitionApplication'])->name('volunteers.applications.transitions.store');
        Route::post('volunteers/{volunteer}/applications/{application}/credentials', [VolunteerOpportunityController::class, 'storeCredential'])->name('volunteers.applications.credentials.store');
        Route::post('volunteers/{volunteer}/shifts', [VolunteerOpportunityController::class, 'storeShift'])->name('volunteers.shifts.store');
        Route::post('volunteers/{volunteer}/assignments/{assignment}/transitions', [VolunteerOpportunityController::class, 'transitionAssignment'])->name('volunteers.assignments.transitions.store');
        Route::post('supporter-journeys/{supporter_journey}/approve', [SupporterJourneyController::class, 'approve'])->name('supporter-journeys.approve');
        Route::post('supporter-journeys/{supporter_journey}/dispatch', [SupporterJourneyController::class, 'dispatch'])->name('supporter-journeys.dispatch');
        Route::post('supporter-journeys/{supporter_journey}/transitions', [SupporterJourneyController::class, 'transitionJourney'])->name('supporter-journeys.transitions.store');
        Route::post('supporter-journeys/{supporter_journey}/recipients/{recipient}/transitions', [SupporterJourneyController::class, 'transition'])->name('supporter-journeys.recipients.transitions.store');
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
        Route::post('cases/{case}/documents', [CaseDocumentController::class, 'store'])->middleware('throttle:'.config('case_documents.max_attempts_per_minute').',1')->name('cases.documents.store');
        Route::post('cases/{case}/documents/{document}/replacement', [CaseDocumentController::class, 'replace'])->middleware('throttle:'.config('case_documents.max_attempts_per_minute').',1')->name('cases.documents.replace');
        Route::get('cases/{case}/documents/{document}/download', [CaseDocumentController::class, 'download'])->name('cases.documents.download');
        Route::get('programs/{program}/cases/export', CaseExportController::class)->name('programs.cases.export');
        Route::post('cases/{case}/items', [CaseItemController::class, 'store'])->name('cases.items.store');
        Route::post('cases/{case}/items/{subjectType}/{subject}/transitions', [CaseWorkflowController::class, 'store'])->name('cases.items.transitions.store');
        Route::post('duplicate-reviews/{duplicate_review}', [PartyDuplicateReviewController::class, 'store'])->name('duplicate-reviews.store');
        Route::delete('duplicate-reviews/{duplicate_review}', [PartyDuplicateReviewController::class, 'destroy'])->name('duplicate-reviews.destroy');
        Route::post('parties/{party}/consents', [PartyConsentController::class, 'store'])->name('parties.consents.store');
        Route::post('parties/{party}/portal-access-grants', [OrganisationPortalAccessGrantController::class, 'store'])->name('parties.portal-access-grants.store');
        Route::post('parties/{party}/addresses', [PartyAddressController::class, 'store'])->name('parties.addresses.store');
        Route::post('parties/{party}/relationships', [PartyRelationshipController::class, 'store'])->name('parties.relationships.store');
        Route::post('parties/{party}/safe-contact-instructions', [PartySafeContactInstructionController::class, 'store'])->name('parties.safe-contact-instructions.store');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('settings/organisations/{organisation}/demo-reset', [SandboxOrganisationController::class, 'reset'])
        ->middleware([EnsureStaffSecurityRequirements::class, EnsureRecentPassword::class, EnsureOrganisationMembership::class, UseOrganisationContext::class, EnsureOrganisationAccess::class.':administration'])
        ->name('demo.organisations.reset');
    Route::post('invitations/{invitation}/accept', [OrganisationInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [OrganisationInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
