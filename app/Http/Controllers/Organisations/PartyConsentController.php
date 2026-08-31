<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Parties\RecordPartyConsent;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\RecordPartyConsentRequest;
use App\Models\Organisation;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PartyConsentController extends Controller
{
    public function store(
        RecordPartyConsentRequest $request,
        Organisation $currentOrganisation,
        string $party,
        RecordPartyConsent $recordPartyConsent,
    ): RedirectResponse {
        $party = Party::query()->where('uuid', $party)->firstOrFail();
        Gate::authorize('recordConsent', $party);
        $purpose = ConsentPurpose::from($request->string('purpose')->toString());
        abort_if(Gate::allows('supporterSafe', $party) && $purpose !== ConsentPurpose::SupporterUpdates, 403);
        $recordPartyConsent->handle($party, [
            'purpose' => $purpose,
            'channel' => $request->filled('channel') ? ConsentChannel::from($request->string('channel')->toString()) : ConsentChannel::NotApplicable,
            'decision' => ConsentDecision::from($request->string('decision')->toString()),
            'wording_version' => $request->string('wording_version')->toString(),
            'wording' => $request->string('wording')->toString(),
            'source' => $request->string('source')->toString(),
            'occurred_at' => $request->string('occurred_at')->toString(),
        ], $request->user());

        return back();
    }
}
