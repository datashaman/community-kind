<?php

namespace App\Actions\CaseConfidentiality;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Authorization\CaseAccess;
use App\Enums\TenantAuditEventType;
use App\Models\RestrictedAccessGrant;
use App\Models\RestrictedAccessRevocation;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class RevokeRestrictedAccess
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly CaseAccess $access,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(RestrictedAccessGrant $grant, string $reason, User $actor): RestrictedAccessRevocation
    {
        return DB::transaction(function () use ($grant, $reason, $actor): RestrictedAccessRevocation {
            $grant = RestrictedAccessGrant::query()->lockForUpdate()->findOrFail($grant->id);
            $this->context->ensureOwns($grant->organisation_id);

            if (! $this->access->canManageProgram($actor, $grant->program)) {
                throw new LogicException('Only a scoped Program manager may revoke restricted access.');
            }

            $existing = $grant->revocation()->first();
            if ($existing !== null) {
                return $existing;
            }

            $revocation = RestrictedAccessRevocation::query()->create([
                'restricted_access_grant_id' => $grant->id,
                'reason' => $reason,
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->id,
            ]);
            $this->recordAudit->handle($grant->program->organisation, TenantAuditEventType::RestrictedAccessRevoked, 'restricted_access_grant', $grant->id, [
                'grant_id' => $grant->id,
                'reason' => $reason,
            ], $actor);

            return $revocation;
        });
    }
}
