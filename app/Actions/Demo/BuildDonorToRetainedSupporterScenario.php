<?php

namespace App\Actions\Demo;

use App\Actions\Donations\CreateDonation;
use App\Actions\Parties\RecordPartyConsent;
use App\Donations\DonationPaymentProvider;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\DonationFrequency;
use App\Enums\DonationSimulationScenario;
use App\Models\AudienceSegment;
use App\Models\Donation;
use App\Models\DonationFund;
use App\Models\FundraisingCampaign;
use App\Models\Party;
use App\Models\PartyConsent;
use App\Models\User;

class BuildDonorToRetainedSupporterScenario
{
    public function __construct(
        private readonly CreateDonation $createDonation,
        private readonly DonationPaymentProvider $paymentProvider,
        private readonly RecordPartyConsent $recordPartyConsent,
    ) {}

    public function handle(Party $donor, User $actor): Donation
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
        $consent = PartyConsent::query()
            ->where('party_id', $donor->id)
            ->where('purpose', ConsentPurpose::SupporterUpdates)
            ->where('channel', ConsentChannel::Email)
            ->where('source', 'winter-warmth-demo')
            ->first();
        if ($consent === null) {
            $this->recordPartyConsent->handle($donor, [
                'purpose' => ConsentPurpose::SupporterUpdates,
                'channel' => ConsentChannel::Email,
                'decision' => ConsentDecision::Granted,
                'wording_version' => 'supporter-updates-v1',
                'wording' => 'I agree to receive simulated supporter updates by email for the Winter Warmth Demo Appeal.',
                'source' => 'winter-warmth-demo',
                'occurred_at' => now()->toAtomString(),
            ], $actor);
        }
        AudienceSegment::query()->updateOrCreate(
            ['organisation_id' => $donor->organisation_id, 'name' => 'Winter Warmth retained supporters'],
            [
                'criteria' => [
                    'purpose' => ConsentPurpose::SupporterUpdates->value,
                    'channel' => ConsentChannel::Email->value,
                    'role' => 'donor',
                    'service_area' => null,
                    'interest' => null,
                    'donation_activity' => true,
                    'campaign_source' => 'showcase_fixture',
                ],
                'created_by_user_id' => $actor->id,
            ],
        );

        return $donation->refresh();
    }
}
