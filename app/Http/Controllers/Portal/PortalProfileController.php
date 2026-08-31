<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\UpdatePortalProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdatePortalProfileRequest;
use App\Models\PortalAccessGrant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PortalProfileController extends Controller
{
    public function update(UpdatePortalProfileRequest $request, UpdatePortalProfile $updatePortalProfile): RedirectResponse
    {
        $grant = $request->attributes->get('portal_access_grant');
        abort_unless($grant instanceof PortalAccessGrant, 404);
        $updatePortalProfile->handle($grant, [
            'display_name' => $request->string('display_name')->toString(),
            'email' => $request->filled('email') ? $request->string('email')->toString() : null,
            'telephone' => $request->filled('telephone') ? $request->string('telephone')->toString() : null,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return back();
    }
}
