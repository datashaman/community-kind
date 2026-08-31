<?php

namespace App\Actions\Portal;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\PartyKind;
use App\Enums\TenantAuditEventType;
use App\Models\Party;
use App\Models\PortalAccessGrant;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class IssuePortalAccessGrant
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    /** @return array{grant: PortalAccessGrant, token: string} */
    public function handle(Party $party, User $user, User $actor): array
    {
        $this->context->ensureOwns($party->organisation_id);

        if ($party->kind !== PartyKind::Person || $party->trashed()) {
            throw new LogicException('Portal access requires an active Person Party.');
        }

        if (! $user->hasVerifiedEmail()) {
            throw new LogicException('Portal access requires a verified User.');
        }

        return DB::transaction(function () use ($actor, $party, $user): array {
            $party = Party::query()->lockForUpdate()->findOrFail($party->id);
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $user->hasVerifiedEmail()) {
                throw new LogicException('Portal access requires a verified User.');
            }

            $replacedGrants = PortalAccessGrant::query()
                ->where(fn ($query) => $query
                    ->where('person_party_id', $party->id)
                    ->orWhere('user_id', $user->id))
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();
            $replacedGrants->each(function (PortalAccessGrant $grant) use ($actor, $party): void {
                $grant->update([
                    'revoked_at' => now(),
                    'revoked_by_user_id' => $actor->id,
                    'access_version' => $grant->access_version + 1,
                ]);
                $this->recordAudit->handle(
                    $party->organisation,
                    TenantAuditEventType::PortalAccessRevoked,
                    'portal_access_grant',
                    $grant->id,
                    [
                        'grant_id' => $grant->id,
                        'party_uuid' => $grant->personParty->uuid,
                        'user_id' => $grant->user_id,
                    ],
                    $actor,
                );
            });

            $plainTextToken = Str::random(64);
            $grant = PortalAccessGrant::query()->create([
                'organisation_id' => $party->organisation_id,
                'user_id' => $user->id,
                'person_party_id' => $party->id,
                'token_hash' => hash('sha256', $plainTextToken),
                'access_version' => 1,
                'token_expires_at' => now()->addMinutes((int) config('portal.link_lifetime_minutes')),
                'created_by_user_id' => $actor->id,
            ]);

            $this->recordAudit->handle(
                $party->organisation,
                TenantAuditEventType::PortalAccessIssued,
                'portal_access_grant',
                $grant->id,
                [
                    'grant_id' => $grant->id,
                    'party_uuid' => $party->uuid,
                    'user_id' => $user->id,
                    'expires_at' => $grant->token_expires_at->toAtomString(),
                ],
                $actor,
            );

            return ['grant' => $grant, 'token' => $plainTextToken];
        });
    }
}
