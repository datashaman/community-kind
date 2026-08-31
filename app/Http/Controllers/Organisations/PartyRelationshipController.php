<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Parties\CreatePartyRelationship;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StorePartyRelationshipRequest;
use App\Models\Organisation;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PartyRelationshipController extends Controller
{
    public function store(
        StorePartyRelationshipRequest $request,
        Organisation $currentOrganisation,
        string $party,
        CreatePartyRelationship $createPartyRelationship,
    ): RedirectResponse {
        $party = Party::query()->where('uuid', $party)->firstOrFail();
        Gate::authorize('update', $party);
        $relatedParty = Party::query()->findOrFail($request->integer('related_party_id'));
        $createPartyRelationship->handle($party, [
            'related_party' => $relatedParty,
            'type' => $request->string('type')->toString(),
            'started_at' => $request->filled('started_at') ? $request->string('started_at')->toString() : null,
        ], $request->user());

        return back();
    }
}
