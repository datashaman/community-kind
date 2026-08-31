<?php

namespace App\Actions\Intake;

use App\Actions\CaseDelivery\RecordCaseMetric;
use App\Actions\CaseDelivery\RecordCaseWorkflowTransition;
use App\Actions\Parties\RecordPartyConsent;
use App\Enums\CaseClassification;
use App\Enums\CaseMetricCode;
use App\Enums\CaseWorkflowSubject;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\EligibilityStatus;
use App\Enums\IntakeStatus;
use App\Enums\IntakeUrgency;
use App\Enums\ServiceCaseStatus;
use App\Models\IntakeRequest;
use App\Models\Membership;
use App\Models\PartyConsent;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class TransitionIntakeRequest
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly AssignCaseWorker $assignCaseWorker,
        private readonly RecordPartyConsent $recordPartyConsent,
        private readonly RecordCaseWorkflowTransition $recordCaseWorkflowTransition,
        private readonly RecordCaseMetric $recordCaseMetric,
    ) {}

    /** @param array{urgency?: IntakeUrgency, eligibility_status?: EligibilityStatus, eligibility_context?: array<string, mixed>, risk_flags?: list<string>} $triage */
    public function handle(
        IntakeRequest $intake,
        IntakeStatus $to,
        int $expectedVersion,
        User $actor,
        ?string $reason = null,
        array $triage = [],
        ?Membership $worker = null,
    ): IntakeRequest {
        $this->organisationContext->ensureOwns($intake->organisation_id);

        return DB::transaction(function () use ($intake, $to, $expectedVersion, $actor, $reason, $triage, $worker): IntakeRequest {
            $locked = IntakeRequest::query()->lockForUpdate()->findOrFail($intake->id);

            if ($locked->status === $to && $to === IntakeStatus::Accepted) {
                return $locked;
            }

            if ($locked->version !== $expectedVersion) {
                throw new LogicException('The intake changed while it was being reviewed.');
            }

            if (! in_array($to, $locked->status->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition intake from {$locked->status->value} to {$to->value}.");
            }

            if (in_array($to, [IntakeStatus::Redirected, IntakeStatus::Declined], true) && blank($reason)) {
                throw new LogicException('A reason is required for this transition.');
            }

            if ($to === IntakeStatus::Accepted && ! $this->ensureServiceConsent($locked, $actor)) {
                throw new LogicException('Service consent must be granted before accepting an intake.');
            }

            $from = $locked->status;
            $nextVersion = $locked->version + 1;
            $locked->forceFill([
                'status' => $to,
                'version' => $nextVersion,
                'urgency' => $triage['urgency'] ?? $locked->urgency,
                'eligibility_status' => $triage['eligibility_status'] ?? $locked->eligibility_status,
                'eligibility_context' => $triage['eligibility_context'] ?? $locked->eligibility_context,
                'risk_flags' => $triage['risk_flags'] ?? $locked->risk_flags,
            ])->save();
            $locked->transitions()->create([
                'organisation_id' => $locked->organisation_id,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
                'effective_at' => now(),
                'recorded_at' => now(),
                'version' => $nextVersion,
                'actor_user_id' => $actor->id,
            ]);

            if ($to === IntakeStatus::Accepted) {
                $case = ServiceCase::query()->firstOrCreate(
                    ['intake_request_id' => $locked->id],
                    [
                        'organisation_id' => $locked->organisation_id,
                        'program_id' => $locked->program_id,
                        'party_id' => $locked->party_id,
                        'status' => ServiceCaseStatus::Open,
                        'confidentiality' => CaseClassification::tryFrom(
                            (string) data_get($locked->program->configuration, 'case_default_classification', ''),
                        ) ?? CaseClassification::Confidential,
                        'opened_at' => now(),
                        'created_by_user_id' => $actor->id,
                    ],
                );

                if ($case->wasRecentlyCreated) {
                    $this->recordCaseWorkflowTransition->handle($case, CaseWorkflowSubject::CaseRecord, $case->id, null, ServiceCaseStatus::Open->value, 1, $case->opened_at, $actor);
                    $this->recordCaseMetric->handle($case, CaseMetricCode::CaseOpened, $case->opened_at, "case:{$case->id}:opened");
                }

                if ($worker !== null) {
                    $this->assignCaseWorker->handle($case, $worker, $actor, $reason);
                }
            }

            return $locked->refresh();
        });
    }

    private function ensureServiceConsent(IntakeRequest $intake, User $actor): bool
    {
        $latest = PartyConsent::query()
            ->where('party_id', $intake->party_id)
            ->where('purpose', ConsentPurpose::Service)
            ->latest('occurred_at')
            ->latest('id')
            ->first();

        if ($latest !== null) {
            return $latest->decision === ConsentDecision::Granted;
        }

        $content = json_decode($intake->encrypted_content->reveal(), true, flags: JSON_THROW_ON_ERROR);
        $consent = $content['service_consent'] ?? null;

        if (! is_array($consent) || ($consent['granted'] ?? false) !== true) {
            return false;
        }

        $this->recordPartyConsent->handle($intake->party, [
            'purpose' => ConsentPurpose::Service,
            'decision' => ConsentDecision::Granted,
            'wording_version' => 'service-intake-v1',
            'wording' => 'I agree that my information may be used to assess and provide the requested service.',
            'source' => is_string($consent['source'] ?? null) ? $consent['source'] : 'intake',
            'occurred_at' => is_string($consent['captured_at'] ?? null) ? $consent['captured_at'] : now()->toAtomString(),
        ], $actor);

        return true;
    }
}
