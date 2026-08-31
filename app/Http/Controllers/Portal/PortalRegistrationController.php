<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\CancelSupporterRegistration;
use App\Http\Controllers\Controller;
use App\Models\PortalAccessGrant;
use App\Models\SupporterRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PortalRegistrationController extends Controller
{
    public function destroy(
        Request $request,
        string $publicOrganisation,
        string $registration,
        CancelSupporterRegistration $cancelSupporterRegistration,
    ): RedirectResponse {
        $grant = $request->attributes->get('portal_access_grant');
        abort_unless($grant instanceof PortalAccessGrant, 404);
        $registration = SupporterRegistration::query()->whereKey($registration)->firstOrFail();
        $cancelSupporterRegistration->handle($grant, $registration);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Registration cancelled.')]);

        return back();
    }
}
