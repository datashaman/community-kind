<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Intake\TransitionIntakeRequest;
use App\Enums\EligibilityStatus;
use App\Enums\IntakeStatus;
use App\Enums\IntakeUrgency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\TransitionIntakeRequestRequest;
use App\Models\IntakeRequest;
use App\Models\Membership;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class IntakeTransitionController extends Controller
{
    public function store(
        TransitionIntakeRequestRequest $request,
        Organisation $currentOrganisation,
        string $intake,
        TransitionIntakeRequest $transitionIntakeRequest,
    ): RedirectResponse {
        $intakeRequest = IntakeRequest::query()->findOrFail($intake);
        Gate::authorize('transition', $intakeRequest);
        $worker = $request->filled('worker_membership_id')
            ? Membership::query()->findOrFail($request->integer('worker_membership_id'))
            : null;

        try {
            $transitionIntakeRequest->handle(
                $intakeRequest,
                IntakeStatus::from($request->string('status')->toString()),
                $request->integer('expected_version'),
                $request->user(),
                $request->filled('reason') ? $request->string('reason')->toString() : null,
                array_filter([
                    'urgency' => $request->filled('urgency') ? IntakeUrgency::from($request->string('urgency')->toString()) : null,
                    'eligibility_status' => $request->filled('eligibility_status') ? EligibilityStatus::from($request->string('eligibility_status')->toString()) : null,
                    'eligibility_context' => $request->has('eligibility_context') ? $request->array('eligibility_context') : null,
                    'risk_flags' => $request->has('risk_flags') ? array_values(array_map(strval(...), $request->array('risk_flags'))) : null,
                ], fn (mixed $value): bool => $value !== null),
                $worker,
            );
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return back();
    }
}
