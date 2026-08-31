<?php

namespace App\Actions\Donations;

use App\Donations\DonationPaymentProvider;
use App\Enums\DonationFrequency;
use App\Enums\DonationSimulationScenario;
use App\Enums\PartyKind;
use App\Models\Donation;
use App\Models\DonationFund;
use App\Models\FundraisingCampaign;
use App\Models\Organisation;
use App\Models\Party;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Ramsey\Uuid\Uuid;

class MakePublicSimulatedDonation
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly CreateDonation $createDonation,
        private readonly CreateRecurringMandate $createMandate,
        private readonly DonationPaymentProvider $paymentProvider,
    ) {}

    public function handle(
        Organisation $organisation,
        DonationFund $fund,
        ?FundraisingCampaign $campaign,
        DonationFrequency $frequency,
        int $amountMinor,
        string $idempotencyKey,
    ): Donation {
        $this->context->ensureOwns($organisation->id);
        if (! $fund->is_simulated || ($campaign !== null && ! $campaign->is_simulated) || ! Str::isUuid($idempotencyKey)) {
            throw new LogicException('The public demo accepts simulated fundraising choices only.');
        }

        return DB::transaction(function () use ($organisation, $fund, $campaign, $frequency, $amountMinor, $idempotencyKey): Donation {
            $partyUuid = Uuid::uuid5($organisation->uuid, "public-demo-donation:{$idempotencyKey}")->toString();
            $party = Party::query()->where('uuid', $partyUuid)->first();
            if ($party === null) {
                $party = new Party;
                $party->forceFill([
                    'uuid' => $partyUuid,
                    'organisation_id' => $organisation->id,
                    'kind' => PartyKind::Person,
                    'display_name' => 'Simulated Supporter '.strtoupper(substr(str_replace('-', '', $idempotencyKey), 0, 8)),
                ])->save();
                $party->refresh();
            }
            $donation = $this->createDonation->handle($party, $fund, $campaign, $frequency, $amountMinor, 'ZAR', 'public_demo', $idempotencyKey);

            if ($frequency === DonationFrequency::Monthly) {
                $this->createMandate->handle($donation);
                $donation->unsetRelation('mandate');
            }

            $this->paymentProvider->process($donation->refresh(), DonationSimulationScenario::Success, $idempotencyKey);

            return $donation->refresh()->load(['campaign', 'fund', 'mandate', 'payments.receipt', 'payments.refunds']);
        });
    }
}
