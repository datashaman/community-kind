<?php

namespace App\Donations;

use App\Enums\DonationSimulationScenario;
use App\Models\Donation;
use App\Models\DonationPayment;

interface DonationPaymentProvider
{
    public function process(Donation $donation, DonationSimulationScenario $scenario, string $idempotencyKey): DonationPayment;
}
