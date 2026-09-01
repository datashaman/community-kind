<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Intake\CreateIntakeRequest;
use App\Enums\IntakeUrgency;
use App\Enums\OrganisationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreIntakeRequestRequest;
use App\Models\IntakeRequest;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\Program;
use App\Models\ProgramIntakeField;
use App\Models\ProgramRiskFlag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IntakeRequestController extends Controller
{
    public function index(Request $request, Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [IntakeRequest::class, $currentOrganisation]);
        $programs = Program::query()->with(['intakeFields' => fn ($query) => $query->whereNull('retired_at'), 'riskFlags' => fn ($query) => $query->whereNull('retired_at')])->orderBy('name')->get()->filter(
            fn (Program $program): bool => Gate::allows('create', [IntakeRequest::class, $program]),
        )->values();
        $managerProgramIds = $programs->filter(fn (Program $program): bool => $request->user()->hasOrganisationRole(
            $currentOrganisation,
            OrganisationRole::ProgramManager,
            $program,
        ) && $request->user()->hasProgramAccess($program))->modelKeys();
        $caseWorkerProgramIds = $programs->whereNotIn('id', $managerProgramIds)->modelKeys();
        $membershipId = $request->user()->organisationMembership($currentOrganisation)?->id;
        $intakes = IntakeRequest::query()
            ->with(['party:id,uuid,display_name', 'program:id,name,slug', 'serviceCase.assignments.membership.user'])
            ->where(function (Builder $query) use ($managerProgramIds, $caseWorkerProgramIds, $membershipId): void {
                $query->whereIn('program_id', $managerProgramIds)
                    ->orWhere(function (Builder $query) use ($caseWorkerProgramIds, $membershipId): void {
                        $query->whereIn('program_id', $caseWorkerProgramIds)
                            ->whereHas('serviceCase.assignments', fn (Builder $query) => $query
                                ->where('membership_id', $membershipId)
                                ->where('status', 'active'));
                    });
            })
            ->latest()
            ->paginate(25)
            ->through(fn (IntakeRequest $intake): array => $this->summary($intake));

        return Inertia::render('intakes/index', [
            'intakes' => $intakes,
            'programs' => $programs->map(fn (Program $program): array => [
                'id' => $program->id,
                'name' => $program->name,
                'intakeFields' => $program->intakeFields->map(fn (ProgramIntakeField $field): array => [
                    'key' => $field->key,
                    'label' => $field->label,
                    'fieldType' => $field->field_type->value,
                    'required' => $field->is_required,
                ])->values(),
                'riskFlags' => $program->riskFlags->map(fn (ProgramRiskFlag $flag): array => ['key' => $flag->key, 'label' => $flag->label])->values(),
            ]),
            'parties' => Party::query()
                ->whereHas('programs', fn (Builder $query) => $query->whereIn('programs.id', $programs->modelKeys()))
                ->orderBy('display_name')
                ->limit(250)
                ->get(['uuid', 'display_name'])
                ->map(fn (Party $party): array => ['uuid' => $party->uuid, 'displayName' => $party->display_name]),
        ]);
    }

    public function store(
        StoreIntakeRequestRequest $request,
        Organisation $currentOrganisation,
        CreateIntakeRequest $createIntakeRequest,
    ): RedirectResponse {
        $program = Program::query()->findOrFail($request->integer('program_id'));
        $party = Party::query()->where('uuid', $request->string('party_uuid')->toString())->firstOrFail();
        Gate::authorize('create', [IntakeRequest::class, $program]);
        $intake = $createIntakeRequest->handle($currentOrganisation, $program, $party, [
            'source' => $request->string('source')->toString(),
            'narrative' => $request->string('narrative')->toString(),
            'presenting_needs' => $request->string('presenting_needs')->toString(),
            'urgency' => IntakeUrgency::from($request->string('urgency')->toString()),
            'intake_fields' => $request->array('intake_fields'),
            'eligibility_context' => [],
            'risk_flags' => array_values(array_map(strval(...), $request->array('risk_flags'))),
            'email' => $request->filled('email') ? $request->string('email')->toString() : null,
            'telephone' => $request->filled('telephone') ? $request->string('telephone')->toString() : null,
            'idempotency_key' => $request->filled('idempotency_key') ? $request->string('idempotency_key')->toString() : null,
            'consent_granted' => $request->boolean('consent_granted'),
            'consent_source' => $request->string('consent_source')->toString(),
        ], $request->user());

        return Gate::allows('view', $intake)
            ? to_route('intakes.show', [$currentOrganisation, $intake])
            : to_route('intakes.index', $currentOrganisation);
    }

    public function show(Request $request, Organisation $currentOrganisation, string $intake): Response
    {
        $intakeRequest = IntakeRequest::query()->findOrFail($intake);
        Gate::authorize('view', $intakeRequest);
        $intakeRequest->load([
            'party:id,uuid,display_name',
            'program:id,organisation_id,name,slug',
            'program.intakeFields',
            'program.eligibilityQuestions',
            'program.riskFlags',
            'transitions' => fn ($query) => $query->orderBy('version'),
            'duplicateReviews.candidateParty:id,uuid,display_name',
            'serviceCase.assignments' => fn ($query) => $query->orderBy('started_at'),
            'serviceCase.assignments.membership.user:id,name',
        ]);
        $content = json_decode($intakeRequest->encrypted_content->reveal(), true, flags: JSON_THROW_ON_ERROR);

        return Inertia::render('intakes/show', [
            'intake' => [
                ...$this->summary($intakeRequest),
                'version' => $intakeRequest->version,
                'narrative' => $content['narrative'] ?? '',
                'presentingNeeds' => $content['presenting_needs'] ?? '',
                'intakeFields' => is_array($content['intake_fields'] ?? null) ? $content['intake_fields'] : [],
                'eligibilityContext' => $intakeRequest->eligibility_context,
                'riskFlags' => $intakeRequest->risk_flags,
                'intakeFieldDefinitions' => $intakeRequest->program->intakeFields->map(fn ($field): array => [
                    'key' => $field->key,
                    'label' => $field->label,
                    'fieldType' => $field->field_type->value,
                    'retired' => $field->retired_at !== null,
                ])->values(),
                'eligibilityQuestions' => $intakeRequest->program->eligibilityQuestions
                    ->filter(fn ($question): bool => $question->retired_at === null || array_key_exists($question->key, $intakeRequest->eligibility_context))
                    ->map(fn ($question): array => [
                        'key' => $question->key,
                        'label' => $question->label,
                        'required' => $question->is_required,
                        'retired' => $question->retired_at !== null,
                    ])->values(),
                'riskFlagDefinitions' => $intakeRequest->program->riskFlags
                    ->filter(fn ($flag): bool => $flag->retired_at === null || in_array($flag->key, $intakeRequest->risk_flags, true))
                    ->map(fn ($flag): array => ['key' => $flag->key, 'label' => $flag->label, 'retired' => $flag->retired_at !== null])
                    ->values(),
                'transitions' => $intakeRequest->transitions->map(fn ($transition): array => [
                    'id' => $transition->id,
                    'from' => $transition->from_status?->value,
                    'to' => $transition->to_status->value,
                    'reason' => $transition->reason,
                    'effectiveAt' => $transition->effective_at->toAtomString(),
                    'version' => $transition->version,
                ]),
                'duplicateReviews' => $intakeRequest->duplicateReviews->map(fn ($review): array => [
                    'id' => $review->id,
                    'candidate' => ['uuid' => $review->candidateParty->uuid, 'displayName' => $review->candidateParty->display_name],
                    'matchedFields' => $review->matched_fields,
                    'decision' => $review->decision->value,
                    'reversedAt' => $review->reversed_at?->toAtomString(),
                ]),
                'case' => $intakeRequest->serviceCase === null ? null : [
                    'id' => $intakeRequest->serviceCase->id,
                    'status' => $intakeRequest->serviceCase->status->value,
                    'assignments' => $intakeRequest->serviceCase->assignments->map(fn ($assignment): array => [
                        'id' => $assignment->id,
                        'worker' => $assignment->membership->user->name,
                        'status' => $assignment->status->value,
                        'startedAt' => $assignment->started_at->toAtomString(),
                        'endedAt' => $assignment->ended_at?->toAtomString(),
                    ]),
                ],
            ],
            'canTransition' => Gate::allows('transition', $intakeRequest),
            'workers' => $this->eligibleWorkers($intakeRequest),
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(IntakeRequest $intake): array
    {
        return [
            'id' => $intake->id,
            'party' => ['uuid' => $intake->party->uuid, 'displayName' => $intake->party->display_name],
            'program' => ['id' => $intake->program->id, 'name' => $intake->program->name],
            'status' => $intake->status->value,
            'urgency' => $intake->urgency->value,
            'eligibilityStatus' => $intake->eligibility_status->value,
            'createdAt' => $intake->created_at?->toAtomString(),
        ];
    }

    /** @return list<array{id: int, name: string}> */
    private function eligibleWorkers(IntakeRequest $intake): array
    {
        return array_values(Membership::query()
            ->with('user:id,name')
            ->where('organisation_id', $intake->organisation_id)
            ->whereNull('ended_at')
            ->get()
            ->filter(fn (Membership $membership): bool => $membership->user->hasOrganisationRole(
                $intake->organisation,
                OrganisationRole::CaseWorker,
                $intake->program,
            ) && $membership->user->hasProgramAccess($intake->program))
            ->map(fn (Membership $membership): array => ['id' => $membership->id, 'name' => $membership->user->name])
            ->values()
            ->all());
    }
}
