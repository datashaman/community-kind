<?php

namespace App\Donations;

use App\Actions\Donations\CreateDonationPayment;
use App\Actions\Donations\RefundDonationPayment;
use App\Actions\Donations\TransitionDonationPayment;
use App\Actions\Donations\TransitionRecurringMandate;
use App\Enums\DonationPaymentStatus;
use App\Enums\DonationSimulationScenario;
use App\Enums\RecurringMandateStatus;
use App\Models\Donation;
use App\Models\DonationPayment;
use App\Models\RecurringMandate;
use LogicException;
use Ramsey\Uuid\Uuid;

class SimulatedDonationPaymentProvider implements DonationPaymentProvider
{
    public function __construct(
        private readonly CreateDonationPayment $createPayment,
        private readonly TransitionDonationPayment $transitionPayment,
        private readonly RefundDonationPayment $refundPayment,
        private readonly TransitionRecurringMandate $transitionMandate,
    ) {}

    public function process(Donation $donation, DonationSimulationScenario $scenario, string $idempotencyKey): DonationPayment
    {
        return match ($scenario) {
            DonationSimulationScenario::Success => $this->successfulAttempt($donation, $idempotencyKey),
            DonationSimulationScenario::Decline => $this->failedAttempt($donation, $idempotencyKey),
            DonationSimulationScenario::TimeoutThenSuccess => $this->timeoutThenSuccess($donation, $idempotencyKey),
            DonationSimulationScenario::PartialRefund => $this->partiallyRefundedAttempt($donation, $idempotencyKey),
            DonationSimulationScenario::RecurringFailure => $this->recurringFailure($donation, $idempotencyKey),
        };
    }

    private function successfulAttempt(Donation $donation, string $idempotencyKey): DonationPayment
    {
        $mandate = $donation->mandate;
        $payment = $this->createPayment->handle($donation, $this->key($idempotencyKey, 'payment'), $mandate);
        $payment = $this->transitionPayment->handle($payment, DonationPaymentStatus::Pending, $this->key($idempotencyKey, 'pending'), now());
        $payment = $this->transitionPayment->handle($payment, DonationPaymentStatus::Succeeded, $this->key($idempotencyKey, 'succeeded'), now());

        if ($mandate !== null && in_array($mandate->fresh()->status, [RecurringMandateStatus::Pending, RecurringMandateStatus::PaymentFailed], true)) {
            $this->transitionMandate->handle($mandate->fresh(), RecurringMandateStatus::Active, $this->key($idempotencyKey, 'mandate-active'), now(), $payment);
        }

        return $payment;
    }

    private function failedAttempt(Donation $donation, string $idempotencyKey): DonationPayment
    {
        $payment = $this->createPayment->handle($donation, $this->key($idempotencyKey, 'payment'), $donation->mandate);
        $payment = $this->transitionPayment->handle($payment, DonationPaymentStatus::Pending, $this->key($idempotencyKey, 'pending'), now());

        return $this->transitionPayment->handle($payment, DonationPaymentStatus::Failed, $this->key($idempotencyKey, 'failed'), now());
    }

    private function timeoutThenSuccess(Donation $donation, string $idempotencyKey): DonationPayment
    {
        $this->failedAttempt($donation, $this->key($idempotencyKey, 'timeout-attempt'));

        return $this->successfulAttempt($donation, $this->key($idempotencyKey, 'retry-attempt'));
    }

    private function partiallyRefundedAttempt(Donation $donation, string $idempotencyKey): DonationPayment
    {
        $payment = $this->successfulAttempt($donation, $idempotencyKey);
        $this->refundPayment->handle($payment, max(1, intdiv($payment->amount_minor, 2)), $this->key($idempotencyKey, 'partial-refund'), now());

        return $payment->refresh();
    }

    private function recurringFailure(Donation $donation, string $idempotencyKey): DonationPayment
    {
        $mandate = $donation->mandate;
        if (! $mandate instanceof RecurringMandate || $mandate->status !== RecurringMandateStatus::Active) {
            throw new LogicException('The recurring-failure scenario requires an active simulated Recurring Mandate.');
        }

        $payment = $this->failedAttempt($donation, $idempotencyKey);
        $this->transitionMandate->handle($mandate, RecurringMandateStatus::PaymentFailed, $this->key($idempotencyKey, 'mandate-failed'), now(), $payment);

        return $payment;
    }

    private function key(string $namespace, string $name): string
    {
        return Uuid::uuid5($namespace, $name)->toString();
    }
}
