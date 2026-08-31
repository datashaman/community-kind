<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\CaseDelivery\CreateCaseAppointment;
use App\Actions\CaseDelivery\CreateCaseGoal;
use App\Actions\CaseDelivery\CreateCaseService;
use App\Actions\CaseDelivery\CreateCaseTask;
use App\Actions\CaseDelivery\CreateExternalReferral;
use App\Actions\CaseDelivery\RecordCaseInteraction;
use App\Actions\CaseDelivery\SaveCaseNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreCaseItemRequest;
use App\Models\CaseNote;
use App\Models\Organisation;
use App\Models\ServiceCase;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class CaseItemController extends Controller
{
    public function store(
        StoreCaseItemRequest $request,
        Organisation $currentOrganisation,
        string $case,
        CreateCaseGoal $createGoal,
        CreateCaseService $createService,
        CreateExternalReferral $createReferral,
        CreateCaseTask $createTask,
        CreateCaseAppointment $createAppointment,
        RecordCaseInteraction $recordInteraction,
        SaveCaseNote $saveNote,
    ): RedirectResponse {
        $serviceCase = ServiceCase::query()->findOrFail($case);
        Gate::authorize('update', $serviceCase);

        try {
            match ($request->string('kind')->toString()) {
                'goal' => $createGoal->handle($serviceCase, $request->string('title')->toString(), $request->string('description')->toString(), $this->date($request->input('target_at')), $request->user()),
                'service' => $createService->handle($serviceCase, $request->string('service_code')->toString(), $request->string('summary')->toString(), $this->date($request->input('scheduled_for')), $request->user()),
                'referral' => $createReferral->handle($serviceCase, $request->string('destination')->toString(), $request->string('purpose')->toString(), $request->string('minimum_necessary')->toString(), $request->string('sharing_authority')->toString(), $request->user()),
                'task' => $createTask->handle($serviceCase, $request->string('title')->toString(), $request->string('description')->toString(), $this->date($request->input('due_at')), $request->user()),
                'appointment' => $createAppointment->handle($serviceCase, $request->string('summary')->toString(), $request->string('location')->toString(), $this->date($request->input('scheduled_at')) ?? now(), $request->user()),
                'interaction' => $recordInteraction->handle($serviceCase, $request->string('interaction_type')->toString(), $request->string('summary')->toString(), $this->date($request->input('occurred_at')) ?? now(), $request->user()),
                'note' => $saveNote->handle($serviceCase, $request->string('content')->toString(), $request->user(), $this->correctedNote($serviceCase, $request->input('corrects_note_id'))),
                default => throw new LogicException('Unsupported Case item type.'),
            };
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['item' => $exception->getMessage()]);
        }

        return back();
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }

    private function correctedNote(ServiceCase $case, mixed $id): ?CaseNote
    {
        return is_string($id) && $id !== '' ? $case->notes()->findOrFail($id) : null;
    }
}
