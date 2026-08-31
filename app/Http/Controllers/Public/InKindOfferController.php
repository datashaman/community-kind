<?php

namespace App\Http\Controllers\Public;

use App\Actions\Engagement\RecordInKindOffer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreInKindOfferRequest;
use App\Models\Organisation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InKindOfferController extends Controller
{
    public function create(Request $request): View
    {
        return view('public.in-kind.create', ['organisation' => $this->organisation($request)]);
    }

    public function store(StoreInKindOfferRequest $request, RecordInKindOffer $record): View
    {
        $validated = $request->validated();
        $organisation = $this->organisation($request);
        $offer = $record->handle($organisation, ['name' => (string) $validated['name'], 'email' => (string) $validated['email'], 'category' => (string) $validated['category'], 'description' => (string) $validated['description'], 'quantity' => (float) $validated['quantity'], 'unit' => (string) $validated['unit'], 'estimated_value_minor' => isset($validated['estimated_value_minor']) ? (int) $validated['estimated_value_minor'] : null, 'currency' => isset($validated['currency']) ? strtoupper((string) $validated['currency']) : null, 'condition' => (string) $validated['condition'], 'consent_email' => (bool) $validated['consent_email']]);

        return view('public.in-kind.confirmation', compact('organisation', 'offer'));
    }

    private function organisation(Request $request): Organisation
    {
        $organisation = $request->attributes->get('public_organisation');
        abort_unless($organisation instanceof Organisation, 404);

        return $organisation;
    }
}
