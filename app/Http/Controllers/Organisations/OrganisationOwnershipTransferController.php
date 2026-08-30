<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Organisations\AcceptOrganisationOwnershipTransfer;
use App\Actions\Organisations\NominateOrganisationOwnershipTransfer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\AcceptOrganisationOwnershipTransferRequest;
use App\Http\Requests\Organisations\CreateOrganisationOwnershipTransferRequest;
use App\Models\Organisation;
use App\Models\OrganisationOwnershipTransfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrganisationOwnershipTransferController extends Controller
{
    public function store(
        CreateOrganisationOwnershipTransferRequest $request,
        Organisation $organisation,
        NominateOrganisationOwnershipTransfer $nominateOrganisationOwnershipTransfer,
    ): RedirectResponse {
        $nominee = User::query()->findOrFail($request->integer('nominee_user_id'));
        $nominateOrganisationOwnershipTransfer->handle($organisation, $request->user(), $nominee);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ownership transfer nominated.')]);

        return to_route('organisations.edit', $organisation);
    }

    public function update(
        AcceptOrganisationOwnershipTransferRequest $request,
        Organisation $organisation,
        OrganisationOwnershipTransfer $transfer,
        AcceptOrganisationOwnershipTransfer $acceptOrganisationOwnershipTransfer,
    ): RedirectResponse {
        abort_unless($transfer->organisation_id === $organisation->id, 404);

        $acceptOrganisationOwnershipTransfer->handle($transfer, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ownership transfer accepted.')]);

        return to_route('organisations.edit', $organisation);
    }
}
