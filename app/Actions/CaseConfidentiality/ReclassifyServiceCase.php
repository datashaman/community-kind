<?php

namespace App\Actions\CaseConfidentiality;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Authorization\CaseAccess;
use App\Enums\CaseClassification;
use App\Enums\TenantAuditEventType;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReclassifyServiceCase
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly CaseAccess $access,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(ServiceCase $case, CaseClassification $classification, string $reason, User $actor): ServiceCase
    {
        return DB::transaction(function () use ($case, $classification, $reason, $actor): ServiceCase {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->context->ensureOwns($case->organisation_id);

            if (! $this->access->canManageAccess($actor, $case)) {
                throw new LogicException('Only a Program manager may reclassify a Case.');
            }

            $from = $case->confidentiality;
            if ($from === $classification) {
                return $case;
            }

            if ($from === CaseClassification::HighlyRestricted
                && ! $this->access->canViewSensitive($actor, $case)) {
                throw new LogicException('Lowering Case sensitivity requires current restricted access.');
            }

            $case->update(['confidentiality' => $classification]);
            $this->recordAudit->handle($case->organisation, TenantAuditEventType::CaseReclassified, 'service_case', $case->id, [
                'case_id' => $case->id,
                'from' => $from->value,
                'to' => $classification->value,
                'reason' => $reason,
            ], $actor);

            return $case->refresh();
        });
    }
}
