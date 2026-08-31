<?php

namespace App\Actions\Donations;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\DonationPaymentStatus;
use App\Enums\PartyTimelineEventType;
use App\Enums\RecurringMandateStatus;
use App\Enums\TenantAuditEventType;
use App\Models\DonationPayment;
use App\Models\RecurringMandate;
use App\Models\RecurringMandateEvent;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Ramsey\Uuid\Uuid;

class TransitionRecurringMandate
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordPartyTimelineEvent $recordTimeline,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(RecurringMandate $mandate, RecurringMandateStatus $to, string $idempotencyKey, CarbonInterface $occurredAt, ?DonationPayment $evidence = null, ?User $actor = null): RecurringMandate
    {
        $this->context->ensureOwns($mandate->organisation_id);
        if (! Str::isUuid($idempotencyKey)) {
            throw new LogicException('A Recurring Mandate transition requires a UUID idempotency key.');
        }

        return DB::transaction(function () use ($mandate, $to, $idempotencyKey, $occurredAt, $evidence, $actor): RecurringMandate {
            $locked = RecurringMandate::query()->with('party')->lockForUpdate()->findOrFail($mandate->id);
            $existingEvent = RecurringMandateEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existingEvent !== null) {
                if ($existingEvent->recurring_mandate_id !== $locked->id || $existingEvent->to_status !== $to) {
                    throw new LogicException('The provider event idempotency key conflicts with another Recurring Mandate transition.');
                }

                return $locked;
            }

            if (! in_array($to, $locked->status->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition Recurring Mandate from {$locked->status->value} to {$to->value}.");
            }

            $lockedEvidence = $evidence === null ? null : DonationPayment::query()->lockForUpdate()->findOrFail($evidence->id);
            if ($to === RecurringMandateStatus::Active && ($lockedEvidence?->recurring_mandate_id !== $locked->id || $lockedEvidence->status !== DonationPaymentStatus::Succeeded)) {
                throw new LogicException('Activating or recovering a Recurring Mandate requires a new successful Donation Payment.');
            }
            if ($to === RecurringMandateStatus::PaymentFailed && ($lockedEvidence?->recurring_mandate_id !== $locked->id || $lockedEvidence->status !== DonationPaymentStatus::Failed)) {
                throw new LogicException('A failed Recurring Mandate requires a failed Donation Payment.');
            }
            $lastFailedPaymentId = $locked->events()->where('to_status', RecurringMandateStatus::PaymentFailed)->latest('occurred_at')->value('donation_payment_id');
            $lastFailedAttempt = $lastFailedPaymentId === null ? null : DonationPayment::query()->whereKey($lastFailedPaymentId)->value('attempt_number');
            if ($to === RecurringMandateStatus::Active && $lastFailedAttempt !== null && $lockedEvidence->attempt_number <= $lastFailedAttempt) {
                throw new LogicException('Recurring Mandate recovery requires a successful Donation Payment after the failure.');
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => $to,
                'version' => $locked->version + 1,
                'cancelled_at' => $to === RecurringMandateStatus::Cancelled ? $occurredAt : null,
            ])->save();
            RecurringMandateEvent::query()->create([
                'organisation_id' => $locked->organisation_id,
                'recurring_mandate_id' => $locked->id,
                'donation_payment_id' => $lockedEvidence?->id,
                'idempotency_key' => $idempotencyKey,
                'provider_event_id' => 'sim_event_'.str_replace('-', '', Uuid::uuid5($locked->id, $idempotencyKey)->toString()),
                'from_status' => $from,
                'to_status' => $to,
                'occurred_at' => $occurredAt,
            ]);
            $this->recordTimeline->handle($locked->party, PartyTimelineEventType::RecurringMandateTransitioned, "Simulated Recurring Mandate changed from {$from->value} to {$to->value}.", $actor, 'recurring_mandate', $locked->id, ['status' => $to->value]);
            $this->recordAudit->handle($locked->party->organisation, TenantAuditEventType::RecurringMandateTransitioned, 'recurring_mandate', $locked->id, [
                'donation_id' => $locked->donation_id,
                'mandate_id' => $locked->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
            ], $actor);

            return $locked->refresh();
        });
    }
}
