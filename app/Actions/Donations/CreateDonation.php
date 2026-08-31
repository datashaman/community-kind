<?php

namespace App\Actions\Donations;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\DonationFrequency;
use App\Enums\PartyBusinessRole;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Models\Donation;
use App\Models\DonationFund;
use App\Models\FundraisingCampaign;
use App\Models\Party;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class CreateDonation
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordPartyTimelineEvent $recordTimeline,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(
        Party $party,
        DonationFund $fund,
        ?FundraisingCampaign $campaign,
        DonationFrequency $frequency,
        int $amountMinor,
        string $currency,
        string $sourceCode,
        string $idempotencyKey,
        ?User $actor = null,
    ): Donation {
        $this->context->ensureOwns($party->organisation_id);
        $this->context->ensureOwns($fund->organisation_id);

        if ($campaign !== null) {
            $this->context->ensureOwns($campaign->organisation_id);
        }

        if ($amountMinor <= 0 || preg_match('/^[A-Z]{3}$/', $currency) !== 1 || blank($sourceCode) || ! Str::isUuid($idempotencyKey)) {
            throw new LogicException('A simulated Donation requires valid immutable money, attribution, and idempotency values.');
        }

        return DB::transaction(function () use ($party, $fund, $campaign, $frequency, $amountMinor, $currency, $sourceCode, $idempotencyKey, $actor): Donation {
            $donation = Donation::query()->firstOrCreate(
                ['organisation_id' => $party->organisation_id, 'idempotency_key' => $idempotencyKey],
                [
                    'party_id' => $party->id,
                    'fundraising_campaign_id' => $campaign?->id,
                    'donation_fund_id' => $fund->id,
                    'frequency' => $frequency,
                    'amount_minor' => $amountMinor,
                    'currency' => $currency,
                    'source_code' => $sourceCode,
                    'is_simulated' => true,
                ],
            );

            $expected = [(int) $party->id, (int) $fund->id, $campaign === null ? null : (int) $campaign->id, $frequency->value, $amountMinor, $currency, $sourceCode];
            $actual = [(int) $donation->party_id, (int) $donation->donation_fund_id, $donation->fundraising_campaign_id === null ? null : (int) $donation->fundraising_campaign_id, $donation->frequency->value, (int) $donation->amount_minor, $donation->currency, $donation->source_code];
            if ($actual !== $expected) {
                throw new LogicException('The Donation idempotency key was already used for different intent.');
            }

            if (! $donation->wasRecentlyCreated) {
                return $donation;
            }

            $party->businessRoles()->firstOrCreate(
                ['role' => PartyBusinessRole::Donor],
                ['organisation_id' => $party->organisation_id],
            );
            $this->recordTimeline->handle($party, PartyTimelineEventType::DonationCreated, 'Simulated Donation intent created.', $actor, 'donation', $donation->id, ['frequency' => $frequency->value]);
            $this->recordAudit->handle($party->organisation, TenantAuditEventType::DonationCreated, 'donation', $donation->id, ['donation_id' => $donation->id, 'frequency' => $frequency->value], $actor);

            return $donation;
        });
    }
}
