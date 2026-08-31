<?php

namespace App\Actions\Donations;

use App\Enums\DonationPaymentStatus;
use App\Enums\RecurringMandateStatus;
use App\Models\Donation;
use App\Models\DonationPayment;
use App\Models\RecurringMandate;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Ramsey\Uuid\Uuid;

class CreateDonationPayment
{
    public function __construct(private readonly OrganisationContext $context) {}

    public function handle(Donation $donation, string $idempotencyKey, ?RecurringMandate $mandate = null): DonationPayment
    {
        $this->context->ensureOwns($donation->organisation_id);
        if (! Str::isUuid($idempotencyKey)) {
            throw new LogicException('A Donation Payment requires a UUID idempotency key.');
        }

        return DB::transaction(function () use ($donation, $idempotencyKey, $mandate): DonationPayment {
            $lockedDonation = Donation::query()->lockForUpdate()->findOrFail($donation->id);
            $existing = DonationPayment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if ($existing->donation_id !== $lockedDonation->id || $existing->recurring_mandate_id !== $mandate?->id) {
                    throw new LogicException('The Donation Payment idempotency key was already used for another attempt.');
                }

                return $existing;
            }

            if ($mandate !== null) {
                $lockedMandate = RecurringMandate::query()->lockForUpdate()->findOrFail($mandate->id);
                if ($lockedMandate->donation_id !== $lockedDonation->id || $lockedMandate->status === RecurringMandateStatus::Cancelled) {
                    throw new LogicException('The Recurring Mandate cannot create this Donation Payment.');
                }
            }

            $attemptNumber = DonationPayment::query()->where('donation_id', $lockedDonation->id)->max('attempt_number') + 1;

            return DonationPayment::query()->create([
                'organisation_id' => $lockedDonation->organisation_id,
                'donation_id' => $lockedDonation->id,
                'recurring_mandate_id' => $mandate?->id,
                'attempt_number' => $attemptNumber,
                'amount_minor' => $lockedDonation->amount_minor,
                'currency' => $lockedDonation->currency,
                'status' => DonationPaymentStatus::Created,
                'version' => 1,
                'idempotency_key' => $idempotencyKey,
                'provider_payment_id' => 'sim_payment_'.str_replace('-', '', Uuid::uuid5($lockedDonation->id, $idempotencyKey)->toString()),
            ]);
        });
    }
}
