<?php

namespace App\Actions\Donations;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\DonationPaymentStatus;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Models\DonationPayment;
use App\Models\DonationPaymentEvent;
use App\Models\DonationRefund;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Ramsey\Uuid\Uuid;

class RefundDonationPayment
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordPartyTimelineEvent $recordTimeline,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(DonationPayment $payment, int $amountMinor, string $idempotencyKey, CarbonInterface $occurredAt, ?User $actor = null): DonationRefund
    {
        $this->context->ensureOwns($payment->organisation_id);
        if ($amountMinor <= 0 || ! Str::isUuid($idempotencyKey)) {
            throw new LogicException('A simulated refund requires a positive amount and UUID idempotency key.');
        }

        return DB::transaction(function () use ($payment, $amountMinor, $idempotencyKey, $occurredAt, $actor): DonationRefund {
            $locked = DonationPayment::query()->with('donation.party')->lockForUpdate()->findOrFail($payment->id);
            $existing = DonationRefund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if ($existing->donation_payment_id !== $locked->id || $existing->amount_minor !== $amountMinor) {
                    throw new LogicException('The refund idempotency key conflicts with another refund.');
                }

                return $existing;
            }

            if (! in_array($locked->status, [DonationPaymentStatus::Succeeded, DonationPaymentStatus::PartiallyRefunded], true)) {
                throw new LogicException('Only a settled Donation Payment can be refunded.');
            }

            $refundedMinor = (int) DonationRefund::query()->where('donation_payment_id', $locked->id)->sum('amount_minor');
            if ($refundedMinor + $amountMinor > $locked->amount_minor) {
                throw new LogicException('Refunds cannot exceed the settled Donation Payment amount.');
            }

            $refund = DonationRefund::query()->create([
                'organisation_id' => $locked->organisation_id,
                'donation_payment_id' => $locked->id,
                'amount_minor' => $amountMinor,
                'currency' => $locked->currency,
                'idempotency_key' => $idempotencyKey,
                'provider_refund_id' => 'sim_refund_'.str_replace('-', '', Uuid::uuid5($locked->id, $idempotencyKey)->toString()),
                'occurred_at' => $occurredAt,
            ]);
            $from = $locked->status;
            $to = $refundedMinor + $amountMinor === $locked->amount_minor
                ? DonationPaymentStatus::Refunded
                : DonationPaymentStatus::PartiallyRefunded;
            $locked->forceFill(['status' => $to, 'version' => $locked->version + 1])->save();
            DonationPaymentEvent::query()->create([
                'organisation_id' => $locked->organisation_id,
                'donation_payment_id' => $locked->id,
                'idempotency_key' => $idempotencyKey,
                'provider_event_id' => 'sim_event_'.str_replace('-', '', Uuid::uuid5($locked->id, $idempotencyKey)->toString()),
                'from_status' => $from,
                'to_status' => $to,
                'occurred_at' => $occurredAt,
            ]);
            $this->recordTimeline->handle($locked->donation->party, PartyTimelineEventType::DonationRefunded, 'Simulated Donation Payment refund recorded.', $actor, 'donation_refund', $refund->id, ['payment_id' => $locked->id]);
            $this->recordAudit->handle($locked->donation->party->organisation, TenantAuditEventType::DonationRefunded, 'donation_refund', $refund->id, ['donation_id' => $locked->donation_id, 'payment_id' => $locked->id, 'refund_id' => $refund->id], $actor);

            return $refund;
        });
    }
}
