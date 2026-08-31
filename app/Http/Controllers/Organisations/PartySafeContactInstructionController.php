<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Parties\StoreSafeContactInstruction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StorePartySafeContactInstructionRequest;
use App\Models\Organisation;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PartySafeContactInstructionController extends Controller
{
    public function store(
        StorePartySafeContactInstructionRequest $request,
        Organisation $currentOrganisation,
        string $party,
        StoreSafeContactInstruction $storeSafeContactInstruction,
    ): RedirectResponse {
        $party = Party::query()->where('uuid', $party)->firstOrFail();
        Gate::authorize('manageSafeContact', $party);
        $storeSafeContactInstruction->handle($party, [
            'instruction' => $request->string('instruction')->toString(),
            'source' => $request->string('source')->toString(),
            'effective_at' => $request->string('effective_at')->toString(),
        ], $request->user());

        return back();
    }
}
