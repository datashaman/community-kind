<?php

namespace App\Actions\CaseConfidentiality;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Authorization\CaseAccess;
use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseClassification;
use App\Enums\TenantAuditEventType;
use App\Models\CaseRiskAssessment;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class RecordCaseRiskAssessment
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly CaseAccess $access,
        private readonly ClassifiedDataEncrypter $encrypter,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(ServiceCase $case, string $content, User $actor): CaseRiskAssessment
    {
        return DB::transaction(function () use ($case, $content, $actor): CaseRiskAssessment {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->context->ensureOwns($case->organisation_id);

            if (! $this->access->canViewSensitive($actor, $case)) {
                throw new LogicException('Recording risk information requires restricted Case access.');
            }

            $assessment = new CaseRiskAssessment;
            $assessment->forceFill([
                'id' => $assessment->newUniqueId(),
                'organisation_id' => $case->organisation_id,
                'service_case_id' => $case->id,
                'type' => 'risk_assessment',
                'classification' => CaseClassification::HighlyRestricted,
                'data_key_version' => $this->encrypter->currentVersion(),
                'effective_at' => now(),
                'recorded_by_user_id' => $actor->id,
            ]);
            $assessment->encrypted_content = new ClassifiedValue($content);
            $assessment->save();
            $this->recordAudit->handle($case->organisation, TenantAuditEventType::CaseRiskRecorded, 'case_risk_assessment', $assessment->id, [
                'case_id' => $case->id,
                'classification' => CaseClassification::HighlyRestricted->value,
            ], $actor);

            return $assessment;
        });
    }
}
