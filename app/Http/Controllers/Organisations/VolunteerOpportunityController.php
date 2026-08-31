<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Volunteering\RecordVolunteerHours;
use App\Actions\Volunteering\TransitionVolunteerApplication;
use App\Actions\Volunteering\TransitionVolunteerAssignment;
use App\Enums\PartyContactType;
use App\Enums\TenantAuditEventType;
use App\Enums\VolunteerApplicationStatus;
use App\Enums\VolunteerAssignmentStatus;
use App\Enums\VolunteerCredentialStatus;
use App\Enums\VolunteerOpportunityStatus;
use App\Enums\VolunteerShiftStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreVolunteerCredentialRequest;
use App\Http\Requests\Organisations\StoreVolunteerOpportunityRequest;
use App\Http\Requests\Organisations\StoreVolunteerShiftRequest;
use App\Http\Requests\Organisations\TransitionVolunteerApplicationRequest;
use App\Http\Requests\Organisations\TransitionVolunteerAssignmentRequest;
use App\Models\Organisation;
use App\Models\VolunteerApplication;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerCredential;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VolunteerOpportunityController extends Controller
{
    public function index(Request $request, Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [VolunteerOpportunity::class, $currentOrganisation]);

        return Inertia::render('volunteers/index', ['opportunities' => VolunteerOpportunity::query()->withCount('applications')->orderByDesc('created_at')->get()->map(fn (VolunteerOpportunity $opportunity): array => ['id' => $opportunity->id, 'title' => $opportunity->title, 'status' => $opportunity->status->value, 'capacity' => $opportunity->capacity, 'applicationsCount' => $opportunity->applications_count])]);
    }

    public function store(StoreVolunteerOpportunityRequest $request, Organisation $currentOrganisation, RecordTenantAuditEvent $recordAudit): RedirectResponse
    {
        Gate::authorize('create', [VolunteerOpportunity::class, $currentOrganisation]);
        $status = VolunteerOpportunityStatus::from($request->string('status')->toString());
        $opportunity = VolunteerOpportunity::query()->create([...$request->validated(), 'status' => $status, 'published_at' => $status === VolunteerOpportunityStatus::Published ? now() : null, 'created_by_user_id' => $request->user()->id]);
        $recordAudit->handle($currentOrganisation, TenantAuditEventType::VolunteerOpportunityCreated, 'volunteer_opportunity', $opportunity->id, ['opportunity_id' => $opportunity->id, 'capacity' => $opportunity->capacity], $request->user());

        return to_route('volunteers.show', [$currentOrganisation, $opportunity]);
    }

    public function show(Request $request, Organisation $currentOrganisation, string $volunteer): Response
    {
        $opportunity = VolunteerOpportunity::query()->with(['applications.party.contactPoints', 'applications.credentials', 'applications.assignments.hours', 'applications.assignments.shift', 'shifts.assignments'])->findOrFail($volunteer);
        Gate::authorize('view', $opportunity);

        return Inertia::render('volunteers/show', ['opportunity' => [
            'id' => $opportunity->id,
            'title' => $opportunity->title,
            'summary' => $opportunity->summary,
            'status' => $opportunity->status->value,
            'capacity' => $opportunity->capacity,
            'applications' => $opportunity->applications->map(fn (VolunteerApplication $application): array => [
                'id' => $application->id,
                'name' => $application->party->display_name,
                'email' => $application->party->contactPoints->first(fn ($contact): bool => $contact->type === PartyContactType::Email)?->encrypted_value->reveal(),
                'status' => $application->status->value,
                'allowedTransitions' => collect($application->status->allowedTransitions())->map(fn (VolunteerApplicationStatus $status): string => $status->value)->all(),
                'onboardingStatus' => $application->onboarding_status->value,
                'interests' => $application->interests,
                'availability' => $application->availability,
                'followUpStatus' => $application->follow_up_status,
                'credentials' => $application->credentials->map(fn (VolunteerCredential $credential): array => ['id' => $credential->id, 'type' => $credential->type, 'status' => $credential->effectiveStatus()->value, 'expiresAt' => $credential->expires_at?->toAtomString(), 'expiresSoon' => $credential->expiresSoon()])->values()->all(),
                'assignments' => $application->assignments->map(fn (VolunteerAssignment $assignment): array => ['id' => $assignment->id, 'status' => $assignment->status->value, 'shiftTitle' => $assignment->shift->title, 'startsAt' => $assignment->shift->starts_at->toAtomString(), 'minutes' => $assignment->hours?->minutes])->values()->all(),
            ]),
            'shifts' => $opportunity->shifts->map(fn (VolunteerShift $shift): array => ['id' => $shift->id, 'title' => $shift->title, 'startsAt' => $shift->starts_at->toAtomString(), 'endsAt' => $shift->ends_at->toAtomString(), 'capacity' => $shift->capacity, 'assignedCount' => $shift->assignments->where('status', VolunteerAssignmentStatus::Confirmed)->count()]),
        ]]);
    }

    public function transitionApplication(TransitionVolunteerApplicationRequest $request, Organisation $currentOrganisation, string $volunteer, string $application, TransitionVolunteerApplication $transition): RedirectResponse
    {
        $opportunity = VolunteerOpportunity::query()->findOrFail($volunteer);
        Gate::authorize('update', $opportunity);
        $application = VolunteerApplication::query()->whereKey($application)->where('volunteer_opportunity_id', $opportunity->id)->firstOrFail();
        $transition->handle($application, VolunteerApplicationStatus::from($request->string('status')->toString()), $request->user());

        return back();
    }

    public function storeCredential(StoreVolunteerCredentialRequest $request, Organisation $currentOrganisation, string $volunteer, string $application, RecordTenantAuditEvent $recordAudit): RedirectResponse
    {
        $opportunity = VolunteerOpportunity::query()->findOrFail($volunteer);
        Gate::authorize('update', $opportunity);
        $application = VolunteerApplication::query()->whereKey($application)->where('volunteer_opportunity_id', $opportunity->id)->firstOrFail();
        $status = VolunteerCredentialStatus::from($request->string('status')->toString());
        $credential = VolunteerCredential::query()->create(['organisation_id' => $currentOrganisation->id, 'volunteer_application_id' => $application->id, 'party_id' => $application->party_id, 'type' => $request->string('type')->toString(), 'status' => $status, 'verified_at' => $status === VolunteerCredentialStatus::Verified ? now() : null, 'expires_at' => $request->date('expires_at'), 'recorded_by_user_id' => $request->user()->id]);
        $recordAudit->handle($currentOrganisation, TenantAuditEventType::VolunteerCredentialRecorded, 'volunteer_credential', $credential->id, ['credential_id' => $credential->id, 'application_id' => $application->id, 'status' => $status->value], $request->user());

        return back();
    }

    public function storeShift(StoreVolunteerShiftRequest $request, Organisation $currentOrganisation, string $volunteer, RecordTenantAuditEvent $recordAudit): RedirectResponse
    {
        $opportunity = VolunteerOpportunity::query()->findOrFail($volunteer);
        Gate::authorize('update', $opportunity);
        $shift = VolunteerShift::query()->create(['organisation_id' => $currentOrganisation->id, 'volunteer_opportunity_id' => $opportunity->id, ...$request->validated(), 'status' => VolunteerShiftStatus::Open, 'created_by_user_id' => $request->user()->id]);
        $recordAudit->handle($currentOrganisation, TenantAuditEventType::VolunteerShiftCreated, 'volunteer_shift', $shift->id, ['shift_id' => $shift->id, 'opportunity_id' => $opportunity->id, 'capacity' => $shift->capacity], $request->user());

        return back();
    }

    public function transitionAssignment(TransitionVolunteerAssignmentRequest $request, Organisation $currentOrganisation, string $volunteer, string $assignment, TransitionVolunteerAssignment $transition, RecordVolunteerHours $recordHours): RedirectResponse
    {
        $opportunity = VolunteerOpportunity::query()->findOrFail($volunteer);
        Gate::authorize('update', $opportunity);
        $assignment = VolunteerAssignment::query()->whereKey($assignment)->whereHas('shift', fn ($query) => $query->where('volunteer_opportunity_id', $opportunity->id))->firstOrFail();
        $status = VolunteerAssignmentStatus::from($request->string('status')->toString());
        $assignment = $transition->handle($assignment, $status, $request->user());
        if ($status === VolunteerAssignmentStatus::Attended) {
            $recordHours->handle($assignment, $request->integer('minutes'), $request->user());
        }

        return back();
    }
}
