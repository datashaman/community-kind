<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Parties\StorePartyAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StorePartyAddressRequest;
use App\Models\Organisation;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PartyAddressController extends Controller
{
    public function store(
        StorePartyAddressRequest $request,
        Organisation $currentOrganisation,
        string $party,
        StorePartyAddress $storePartyAddress,
    ): RedirectResponse {
        $party = Party::query()->where('uuid', $party)->firstOrFail();
        Gate::authorize('update', $party);
        $storePartyAddress->handle($party, [
            'label' => $request->string('label')->toString(),
            'address' => $request->string('address')->toString(),
            'service_area' => $request->filled('service_area') ? $request->string('service_area')->toString() : null,
            'country_code' => strtoupper($request->string('country_code')->toString()),
        ], $request->user());

        return back();
    }
}
