<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Organisations\TransitionOrganisationStatus;
use App\Enums\OrganisationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\TransitionOrganisationRequest;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrganisationLifecycleController extends Controller
{
    public function update(
        TransitionOrganisationRequest $request,
        Organisation $organisation,
        TransitionOrganisationStatus $transitionOrganisationStatus,
    ): RedirectResponse {
        $transitionOrganisationStatus->handle(
            $organisation,
            OrganisationStatus::from($request->validated('status')),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organisation status updated.')]);

        return to_route('organisations.edit', $organisation);
    }
}
