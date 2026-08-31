<?php

namespace App\Http\Controllers\Public;

use App\Actions\Donations\MakePublicSimulatedDonation;
use App\Enums\DonationFrequency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreDonationRequest;
use App\Models\DonationFund;
use App\Models\FundraisingCampaign;
use App\Models\Organisation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;

class DonationController extends Controller
{
    public function create(Request $request): View
    {
        $organisation = $this->organisation($request);

        return view('public.donations.create', [
            'organisation' => $organisation,
            'funds' => DonationFund::query()->where('is_simulated', true)->orderBy('name')->get(),
            'campaigns' => FundraisingCampaign::query()->where('is_simulated', true)->orderBy('name')->get(),
            'idempotencyKey' => Str::uuid()->toString(),
        ]);
    }

    public function store(StoreDonationRequest $request, MakePublicSimulatedDonation $makeDonation): View
    {
        $organisation = $this->organisation($request);
        $validated = $request->validated();
        $fund = DonationFund::query()->findOrFail((int) $validated['fund_id']);
        $campaign = isset($validated['campaign_id'])
            ? FundraisingCampaign::query()->findOrFail((int) $validated['campaign_id'])
            : null;
        try {
            $donation = $makeDonation->handle(
                $organisation,
                $fund,
                $campaign,
                DonationFrequency::from((string) $validated['frequency']),
                (int) $validated['amount_minor'],
                (string) $validated['idempotency_key'],
            );
        } catch (LogicException $exception) {
            abort(409, $exception->getMessage());
        }

        return view('public.donations.show', ['organisation' => $organisation, 'donation' => $donation]);
    }

    private function organisation(Request $request): Organisation
    {
        $organisation = $request->attributes->get('public_organisation');
        abort_unless($organisation instanceof Organisation, 404);

        return $organisation;
    }
}
