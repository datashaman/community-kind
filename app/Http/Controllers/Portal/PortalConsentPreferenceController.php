<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\UpdatePortalConsentPreferences;
use App\Enums\ConsentChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdatePortalConsentPreferencesRequest;
use App\Models\PortalAccessGrant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PortalConsentPreferenceController extends Controller
{
    public function update(
        UpdatePortalConsentPreferencesRequest $request,
        UpdatePortalConsentPreferences $updatePortalConsentPreferences,
    ): RedirectResponse {
        $grant = $request->attributes->get('portal_access_grant');
        abort_unless($grant instanceof PortalAccessGrant, 404);
        $channels = array_values(array_map(
            fn (string $channel): ConsentChannel => ConsentChannel::from($channel),
            $request->array('channels'),
        ));
        $updatePortalConsentPreferences->handle($grant, $channels);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Communication preferences updated.')]);

        return back();
    }
}
