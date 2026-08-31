<?php

namespace App\Actions\Donations;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\DonationPaymentStatus;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Models\DonationPayment;
use App\Models\DonationPaymentEvent;
use App\Models\DonationReceipt;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Ramsey\Uuid\Uuid;

class TransitionDonationPayment
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordPartyTimelineEvent $recordTimeline,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(DonationPayment $payment, DonationPaymentStatus $to, string $idempotencyKey, CarbonInterface $occurredAt, ?User $actor = null): DonationPayment
    {
        $this->context->ensureOwns($payment->organisation_id);
        if (! Str::isUuid($idempotencyKey)) {
            throw new LogicException('A Donation Payment transition requires a UUID idempotency key.');
        }

        return DB::transaction(function () use ($payment, $to, $idempotencyKey, $occurredAt, $actor): DonationPayment {
            $locked = DonationPayment::query()->with('donation.party')->lockForUpdate()->findOrFail($payment->id);
            $existingEvent = DonationPaymentEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existingEvent !== null) {
                if ($existingEvent->donation_payment_id !== $locked->id || $existingEvent->to_status !== $to) {
                    throw new LogicException('The provider event idempotency key conflicts with another transition.');
                }

                return $locked;
            }

            if (! in_array($to, $locked->status->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition Donation Payment from {$locked->status->value} to {$to->value}.");
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => $to,
                'version' => $locked->version + 1,
                'settled_at' => $to === DonationPaymentStatus::Succeeded ? $occurredAt : $locked->settled_at,
            ])->save();
            DonationPaymentEvent::query()->create([
                'organisation_id' => $locked->organisation_id,
                'donation_payment_id' => $locked->id,
                'idempotency_key' => $idempotencyKey,
                'provider_event_id' => 'sim_event_'.str_replace('-', '', Uuid::uuid5($locked->id, $idempotencyKey)->toString()),
                'from_status' => $from,
                'to_status' => $to,
                'occurred_at' => $occurredAt,
            ]);

            if ($to === DonationPaymentStatus::Succeeded) {
                DonationReceipt::query()->firstOrCreate(
                    ['organisation_id' => $locked->organisation_id, 'donation_payment_id' => $locked->id],
                    [
                        'donation_id' => $locked->donation_id,
                        'receipt_number' => 'DEMO-'.strtoupper(substr(str_replace('-', '', $locked->id), 0, 20)),
                        'amount_minor' => $locked->amount_minor,
                        'currency' => $locked->currency,
                        'marker' => 'Demo—Not a tax receipt',
                        'issued_at' => $occurredAt,
                    ],
                );
            }

            $this->recordTimeline->handle($locked->donation->party, PartyTimelineEventType::DonationPaymentTransitioned, "Simulated Donation Payment changed from {$from->value} to {$to->value}.", $actor, 'donation_payment', $locked->id, ['status' => $to->value]);
            $this->recordAudit->handle($locked->donation->party->organisation, TenantAuditEventType::DonationPaymentTransitioned, 'donation_payment', $locked->id, [
                'donation_id' => $locked->donation_id,
                'payment_id' => $locked->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
            ], $actor);

            return $locked->refresh();
        });
    }
}
