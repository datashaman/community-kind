<?php

namespace App\Actions\Donations;

use App\Enums\DonationFrequency;
use App\Enums\RecurringMandateStatus;
use App\Models\Donation;
use App\Models\RecurringMandate;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateRecurringMandate
{
    public function __construct(private readonly OrganisationContext $context) {}

    public function handle(Donation $donation): RecurringMandate
    {
        $this->context->ensureOwns($donation->organisation_id);
        if ($donation->frequency !== DonationFrequency::Monthly) {
            throw new LogicException('Only a monthly Donation can create a Recurring Mandate.');
        }

        return DB::transaction(function () use ($donation): RecurringMandate {
            $locked = Donation::query()->lockForUpdate()->findOrFail($donation->id);

            return RecurringMandate::query()->firstOrCreate(
                ['organisation_id' => $locked->organisation_id, 'donation_id' => $locked->id],
                [
                    'party_id' => $locked->party_id,
                    'amount_minor' => $locked->amount_minor,
                    'currency' => $locked->currency,
                    'interval' => 'monthly',
                    'status' => RecurringMandateStatus::Pending,
                    'version' => 1,
                    'provider_mandate_id' => 'sim_mandate_'.str_replace('-', '', $locked->id),
                ],
            );
        });
    }
}
