<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Portal\IssuePortalAccessGrant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StorePortalAccessGrantRequest;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PortalAccessGrantController extends Controller
{
    public function store(
        StorePortalAccessGrantRequest $request,
        Organisation $currentOrganisation,
        string $party,
        IssuePortalAccessGrant $issuePortalAccessGrant,
    ): RedirectResponse {
        $party = Party::query()->where('uuid', $party)->firstOrFail();
        Gate::authorize('managePortalAccess', $party);
        $user = User::query()->where('email', $request->string('email')->toString())->firstOrFail();
        $issued = $issuePortalAccessGrant->handle($party, $user, $request->user());
        $url = route('portal.access.use', [
            'public_organisation' => $currentOrganisation->slug,
            'token' => $issued['token'],
        ]);

        $request->session()->flash('portal_access_url', $url);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supporter portal link created.')]);

        return back();
    }
}
