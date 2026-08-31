<?php

namespace App\Actions\CaseConfidentiality;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Authorization\CaseAccess;
use App\Enums\OrganisationRole;
use App\Enums\RestrictedAccessPermission;
use App\Enums\TenantAuditEventType;
use App\Models\Membership;
use App\Models\RestrictedAccessGrant;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

class GrantRestrictedAccess
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly CaseAccess $access,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(
        ServiceCase $case,
        Membership $membership,
        RestrictedAccessPermission $permission,
        string $reason,
        User $actor,
        ?CarbonImmutable $expiresAt = null,
    ): RestrictedAccessGrant {
        return DB::transaction(function () use ($case, $membership, $permission, $reason, $actor, $expiresAt): RestrictedAccessGrant {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->context->ensureOwns($case->organisation_id);

            if (! $this->access->canManageAccess($actor, $case)) {
                throw new LogicException('Only a Program manager may grant restricted Case access.');
            }

            if ($membership->organisation_id !== $case->organisation_id || $membership->isHeld()) {
                throw new LogicException('Restricted access may only be granted to an active Membership in this Organisation.');
            }

            $eligible = $membership->hasRole(OrganisationRole::ProgramManager, $case->program)
                || ($membership->hasRole(OrganisationRole::CaseWorker, $case->program)
                    && $case->assignments()->where('membership_id', $membership->id)->where('status', 'active')->exists());

            if (! $eligible) {
                throw new LogicException('Restricted access requires an eligible scoped service Role and active Case assignment where applicable.');
            }

            if ($permission === RestrictedAccessPermission::IdentifiableCaseExport
                && ! $membership->hasRole(OrganisationRole::ProgramManager, $case->program)) {
                throw new LogicException('Only Program managers may receive identifiable Case export access.');
            }

            $serviceCaseId = $permission === RestrictedAccessPermission::SensitiveData ? $case->id : null;
            $grant = RestrictedAccessGrant::query()->create([
                'membership_id' => $membership->id,
                'program_id' => $case->program_id,
                'service_case_id' => $serviceCaseId,
                'permission' => $permission,
                'reason' => $reason,
                'granted_at' => now(),
                'expires_at' => $expiresAt,
                'granted_by_user_id' => $actor->id,
            ]);

            $this->recordAudit->handle($case->organisation, TenantAuditEventType::RestrictedAccessGranted, 'restricted_access_grant', $grant->id, [
                'case_id' => $serviceCaseId,
                'membership_id' => $membership->id,
                'program_id' => $case->program_id,
                'permission' => $permission->value,
                'reason' => $reason,
            ], $actor);

            return $grant;
        });
    }
}
