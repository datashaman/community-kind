<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Donations\TransitionRecurringMandate;
use App\Enums\RecurringMandateStatus;
use App\Http\Controllers\Controller;
use App\Models\PortalAccessGrant;
use App\Models\RecurringMandate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;

class PortalRecurringMandateController extends Controller
{
    public function destroy(
        Request $request,
        string $publicOrganisation,
        string $mandate,
        TransitionRecurringMandate $transitionRecurringMandate,
    ): RedirectResponse {
        $grant = $request->attributes->get('portal_access_grant');
        abort_unless($grant instanceof PortalAccessGrant, 404);
        $mandate = RecurringMandate::query()
            ->whereKey($mandate)
            ->where('organisation_id', $grant->organisation_id)
            ->where('party_id', $grant->person_party_id)
            ->firstOrFail();
        $transitionRecurringMandate->handle(
            $mandate,
            RecurringMandateStatus::Cancelled,
            Uuid::uuid5(Uuid::NAMESPACE_URL, "supporter-portal:{$grant->id}:recurring-mandate:{$mandate->id}:cancel")->toString(),
            now(),
            actor: $grant->user,
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring gift cancelled.')]);

        return back();
    }
}
