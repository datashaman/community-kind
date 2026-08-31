<?php

namespace App\Actions\CaseDelivery;

use App\Enums\CaseMetricCode;
use App\Enums\CaseWorkflowSubject;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\ExternalReferralStatus;
use App\Models\ExternalReferral;
use App\Models\Party;
use App\Models\PartyConsent;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class TransitionExternalReferral
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly RecordCaseWorkflowTransition $recordTransition, private readonly RecordCaseMetric $recordMetric) {}

    public function handle(ExternalReferral $referral, ExternalReferralStatus $to, int $expectedVersion, CarbonInterface $effectiveAt, User $actor, ?string $reason = null): ExternalReferral
    {
        $this->context->ensureOwns($referral->organisation_id);

        return DB::transaction(function () use ($referral, $to, $expectedVersion, $effectiveAt, $actor, $reason): ExternalReferral {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($referral->service_case_id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot transition its work.');
            }
            $locked = ExternalReferral::query()->lockForUpdate()->findOrFail($referral->id);

            if ($locked->status === $to) {
                return $locked;
            }
            if ($locked->version !== $expectedVersion) {
                throw new LogicException('The referral changed while it was being reviewed.');
            }
            if (! in_array($to, $locked->status->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition referral from {$locked->status->value} to {$to->value}.");
            }
            if (in_array($to, [ExternalReferralStatus::NotConnected, ExternalReferralStatus::Cancelled], true) && blank($reason)) {
                throw new LogicException('Not-connected or cancelled referrals require a reason code.');
            }
            if ($to === ExternalReferralStatus::Sent) {
                $content = json_decode($locked->encrypted_content->reveal(), true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($content) || blank($content['destination'] ?? null) || blank($content['purpose'] ?? null) || blank($content['minimum_necessary'] ?? null)) {
                    throw new LogicException('Sending a referral requires destination, purpose, and minimum necessary information.');
                }
                Party::query()->lockForUpdate()->findOrFail($case->party_id);
                $consent = PartyConsent::query()->where('party_id', $case->party_id)->where('purpose', ConsentPurpose::Service)->latest('occurred_at')->latest('id')->first();
                if ($locked->sharing_authority !== 'service_consent' || $consent?->decision !== ConsentDecision::Granted) {
                    throw new LogicException('A current service-consent sharing authority is required when sending a referral.');
                }
            }

            $from = $locked->status;
            $locked->forceFill(['status' => $to, 'version' => $locked->version + 1, 'sent_at' => $to === ExternalReferralStatus::Sent ? $effectiveAt : $locked->sent_at, 'effective_at' => $to->isTerminal() ? $effectiveAt : null, 'terminal_reason' => $to->isTerminal() && $to !== ExternalReferralStatus::Connected ? $reason : null])->save();
            $this->recordTransition->handle($case, CaseWorkflowSubject::Referral, $locked->id, $from->value, $to->value, $locked->version, $effectiveAt, $actor, $reason);

            $code = match ($to) {
                ExternalReferralStatus::Connected => CaseMetricCode::ReferralConnected, ExternalReferralStatus::NotConnected => CaseMetricCode::ReferralNotConnected, default => null
            };
            if ($code !== null) {
                $this->recordMetric->handle($case, $code, $effectiveAt, "referral:{$locked->id}:{$locked->version}");
            }

            return $locked->refresh();
        });
    }
}
