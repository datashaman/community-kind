<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Intake\AssignCaseWorker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\AssignCaseRequest;
use App\Models\IntakeRequest;
use App\Models\Membership;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class CaseAssignmentController extends Controller
{
    public function store(
        AssignCaseRequest $request,
        Organisation $currentOrganisation,
        string $intake,
        AssignCaseWorker $assignCaseWorker,
    ): RedirectResponse {
        $intakeRequest = IntakeRequest::query()->findOrFail($intake);
        Gate::authorize('assign', $intakeRequest);
        $case = $intakeRequest->serviceCase()->firstOrFail();
        $worker = Membership::query()->findOrFail($request->integer('membership_id'));

        try {
            $assignCaseWorker->handle(
                $case,
                $worker,
                $request->user(),
                $request->filled('reason') ? $request->string('reason')->toString() : null,
            );
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['membership_id' => $exception->getMessage()]);
        }

        return back();
    }
}
