<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\CaseDelivery\CarryForwardExternalReferral;
use App\Actions\CaseDelivery\FinalizeCaseNote;
use App\Actions\CaseDelivery\TransitionCaseAppointment;
use App\Actions\CaseDelivery\TransitionCaseGoal;
use App\Actions\CaseDelivery\TransitionCaseService;
use App\Actions\CaseDelivery\TransitionCaseTask;
use App\Actions\CaseDelivery\TransitionExternalReferral;
use App\Enums\CaseAppointmentStatus;
use App\Enums\CaseGoalStatus;
use App\Enums\CaseServiceStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\ExternalReferralStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\TransitionCaseItemRequest;
use App\Models\CaseService;
use App\Models\Organisation;
use App\Models\ServiceCase;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class CaseWorkflowController extends Controller
{
    public function store(
        TransitionCaseItemRequest $request,
        Organisation $currentOrganisation,
        string $case,
        string $subjectType,
        string $subject,
        TransitionCaseGoal $transitionGoal,
        TransitionCaseService $transitionService,
        TransitionExternalReferral $transitionReferral,
        CarryForwardExternalReferral $carryReferral,
        TransitionCaseTask $transitionTask,
        TransitionCaseAppointment $transitionAppointment,
        FinalizeCaseNote $finalizeNote,
    ): RedirectResponse {
        $serviceCase = ServiceCase::query()->findOrFail($case);
        Gate::authorize('update', $serviceCase);
        $status = $request->string('status')->toString();
        $version = $request->integer('expected_version');
        $effectiveAt = CarbonImmutable::parse($request->string('effective_at')->toString());
        $reason = $request->filled('reason') ? $request->string('reason')->toString() : null;

        try {
            match ($subjectType) {
                'goal' => $transitionGoal->handle($serviceCase->goals()->findOrFail($subject), CaseGoalStatus::from($status), $version, $effectiveAt, $request->user(), $reason),
                'service' => $transitionService->handle($serviceCase->services()->findOrFail($subject), CaseServiceStatus::from($status), $version, $effectiveAt, $request->user(), $reason),
                'referral' => $status === 'carry_forward'
                    ? $carryReferral->handle($serviceCase->referrals()->findOrFail($subject), $version, (string) $reason, $effectiveAt, $request->user())
                    : $transitionReferral->handle($serviceCase->referrals()->findOrFail($subject), ExternalReferralStatus::from($status), $version, $effectiveAt, $request->user(), $reason),
                'task' => $transitionTask->handle($serviceCase->tasks()->findOrFail($subject), CaseTaskStatus::from($status), $version, $effectiveAt, $request->user(), $reason),
                'appointment' => $transitionAppointment->handle($serviceCase->appointments()->findOrFail($subject), CaseAppointmentStatus::from($status), $version, $effectiveAt, $request->user(), $reason, $this->completedService($serviceCase, $request->input('completed_service_id'))),
                'note' => $finalizeNote->handle($serviceCase->notes()->findOrFail($subject), $version, $request->user()),
                default => throw new LogicException('Unsupported Case item type.'),
            };
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return back();
    }

    private function completedService(ServiceCase $case, mixed $id): ?CaseService
    {
        return is_string($id) && $id !== '' ? $case->services()->findOrFail($id) : null;
    }
}
