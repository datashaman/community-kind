<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\CaseDelivery\TransitionServiceCase;
use App\Data\Values\ClassifiedValue;
use App\Enums\ServiceCaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\TransitionServiceCaseRequest;
use App\Models\CaseAppointment;
use App\Models\CaseGoal;
use App\Models\CaseInteraction;
use App\Models\CaseNote;
use App\Models\CaseService;
use App\Models\CaseTask;
use App\Models\ExternalReferral;
use App\Models\Organisation;
use App\Models\ServiceCase;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class ServiceCaseController extends Controller
{
    public function show(Request $request, Organisation $currentOrganisation, string $case): Response
    {
        $serviceCase = ServiceCase::query()->findOrFail($case);
        Gate::authorize('view', $serviceCase);
        $serviceCase->load([
            'party:id,uuid,display_name', 'program:id,organisation_id,name,configuration', 'intakeRequest:id',
            'assignments.membership.user:id,name', 'goals', 'services', 'referrals', 'tasks', 'appointments',
            'interactions', 'notes', 'outcome', 'workflowTransitions' => fn ($query) => $query->orderByDesc('recorded_at'),
        ]);

        return Inertia::render('cases/show', [
            'caseRecord' => [
                'id' => $serviceCase->id,
                'version' => $serviceCase->version,
                'status' => $serviceCase->status->value,
                'confidentiality' => $serviceCase->confidentiality,
                'openedAt' => $serviceCase->opened_at->toAtomString(),
                'closedAt' => $serviceCase->closed_at?->toAtomString(),
                'closureReason' => $serviceCase->closure_reason,
                'followUpAt' => $serviceCase->follow_up_at?->toAtomString(),
                'party' => ['uuid' => $serviceCase->party->uuid, 'displayName' => $serviceCase->party->display_name],
                'program' => ['id' => $serviceCase->program->id, 'name' => $serviceCase->program->name, 'configuration' => $serviceCase->program->configuration],
                'intakeId' => $serviceCase->intake_request_id,
                'assignments' => $serviceCase->assignments->map(fn ($assignment): array => ['id' => $assignment->id, 'worker' => $assignment->membership->user->name, 'status' => $assignment->status->value]),
                'goals' => $serviceCase->goals->map(fn (CaseGoal $goal): array => [...$this->item($goal->id, $goal->status->value, $goal->version, $goal->encrypted_content), 'targetAt' => $goal->target_at?->toAtomString(), 'effectiveAt' => $goal->effective_at?->toAtomString(), 'reason' => $goal->terminal_reason]),
                'services' => $serviceCase->services->map(fn (CaseService $service): array => [...$this->item($service->id, $service->status->value, $service->version, $service->encrypted_content), 'serviceCode' => $service->service_code, 'scheduledFor' => $service->scheduled_for?->toAtomString(), 'deliveredAt' => $service->delivered_at?->toAtomString(), 'reason' => $service->terminal_reason]),
                'referrals' => $serviceCase->referrals->map(fn (ExternalReferral $referral): array => [...$this->item($referral->id, $referral->status->value, $referral->version, $referral->encrypted_content), 'sharingAuthority' => $referral->sharing_authority, 'sentAt' => $referral->sent_at?->toAtomString(), 'effectiveAt' => $referral->effective_at?->toAtomString(), 'carriedForwardAt' => $referral->carried_forward_at?->toAtomString(), 'reason' => $referral->terminal_reason ?? $referral->carry_forward_reason]),
                'tasks' => $serviceCase->tasks->map(fn (CaseTask $task): array => [...$this->item($task->id, $task->status->value, $task->version, $task->encrypted_content), 'dueAt' => $task->due_at?->toAtomString(), 'overdue' => $task->status->value === 'open' && $task->due_at?->isPast(), 'effectiveAt' => $task->effective_at?->toAtomString(), 'reason' => $task->terminal_reason]),
                'appointments' => $serviceCase->appointments->map(fn (CaseAppointment $appointment): array => [...$this->item($appointment->id, $appointment->status->value, $appointment->version, $appointment->encrypted_content), 'scheduledAt' => $appointment->scheduled_at->toAtomString(), 'effectiveAt' => $appointment->effective_at?->toAtomString(), 'completedServiceId' => $appointment->completed_service_id, 'reason' => $appointment->terminal_reason]),
                'interactions' => $serviceCase->interactions->map(fn (CaseInteraction $interaction): array => ['id' => $interaction->id, 'type' => $interaction->interaction_type, 'content' => $this->decoded($interaction->encrypted_content), 'occurredAt' => $interaction->occurred_at->toAtomString()]),
                'notes' => $serviceCase->notes->map(fn (CaseNote $note): array => ['id' => $note->id, 'status' => $note->status->value, 'version' => $note->version, 'content' => $note->encrypted_content->reveal(), 'correctsNoteId' => $note->corrects_note_id, 'finalizedAt' => $note->finalized_at?->toAtomString()]),
                'outcome' => $serviceCase->outcome === null ? null : ['measures' => $serviceCase->outcome->measures, 'narrative' => $serviceCase->outcome->encrypted_content->reveal(), 'effectiveAt' => $serviceCase->outcome->effective_at->toAtomString()],
                'transitions' => $serviceCase->workflowTransitions->map(fn ($transition): array => ['id' => $transition->id, 'subjectType' => $transition->subject_type->value, 'from' => $transition->from_status, 'to' => $transition->to_status, 'reason' => $transition->reason, 'effectiveAt' => $transition->effective_at->toAtomString(), 'version' => $transition->version]),
            ],
            'canUpdate' => Gate::allows('update', $serviceCase),
        ]);
    }

    public function transition(TransitionServiceCaseRequest $request, Organisation $currentOrganisation, string $case, TransitionServiceCase $transitionCase): RedirectResponse
    {
        $serviceCase = ServiceCase::query()->findOrFail($case);
        Gate::authorize('update', $serviceCase);

        try {
            $transitionCase->handle($serviceCase, ServiceCaseStatus::from($request->string('status')->toString()), $request->integer('expected_version'), CarbonImmutable::parse($request->string('effective_at')->toString()), $request->user(), [
                'reason' => $request->filled('reason') ? $request->string('reason')->toString() : null,
                'narrative' => $request->filled('narrative') ? $request->string('narrative')->toString() : null,
                'measures' => $request->array('measures'),
                'follow_up_at' => $request->filled('follow_up_at') ? CarbonImmutable::parse($request->string('follow_up_at')->toString()) : null,
            ]);
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return back();
    }

    /** @return array{id: string, status: string, version: int, content: array<string, mixed>} */
    private function item(string $id, string $status, int $version, ClassifiedValue $content): array
    {
        return compact('id', 'status', 'version') + ['content' => $this->decoded($content)];
    }

    /** @return array<string, mixed> */
    private function decoded(ClassifiedValue $content): array
    {
        $decoded = json_decode($content->reveal(), true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
