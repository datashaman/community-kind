<?php

namespace App\Actions\Demo;

use App\Actions\Donations\CreateDonation;
use App\Donations\DonationPaymentProvider;
use App\Enums\DonationFrequency;
use App\Enums\DonationSimulationScenario;
use App\Models\Donation;
use App\Models\DonationFund;
use App\Models\FundraisingCampaign;
use App\Models\Party;

class BuildDonorToRetainedSupporterScenario
{
    public function __construct(
        private readonly CreateDonation $createDonation,
        private readonly DonationPaymentProvider $paymentProvider,
    ) {}

    public function handle(Party $donor): Donation
    {
        $campaign = FundraisingCampaign::query()->updateOrCreate(
            ['organisation_id' => $donor->organisation_id, 'slug' => 'winter-warmth-demo'],
            ['name' => 'Winter Warmth Demo Appeal', 'is_simulated' => true],
        );
        $fund = DonationFund::query()->updateOrCreate(
            ['organisation_id' => $donor->organisation_id, 'slug' => 'community-relief-demo'],
            ['name' => 'Community Relief Demo Fund', 'is_simulated' => true],
        );
        $idempotencyKey = '30000000-0000-4000-8000-000000000002';
        $donation = $this->createDonation->handle(
            $donor,
            $fund,
            $campaign,
            DonationFrequency::OneOff,
            5000,
            'ZAR',
            'showcase_fixture',
            $idempotencyKey,
        );
        $this->paymentProvider->process($donation, DonationSimulationScenario::Success, $idempotencyKey);

        return $donation->refresh();
    }
}
