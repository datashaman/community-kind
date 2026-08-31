<?php

namespace App\Actions\CaseConfidentiality;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Authorization\CaseAccess;
use App\Enums\CaseClassification;
use App\Enums\RestrictedAccessPermission;
use App\Enums\TenantAuditEventType;
use App\Models\Program;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use LogicException;

class ExportIdentifiableCases
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly CaseAccess $access,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(Program $program, User $actor): string
    {
        $this->context->ensureOwns($program->organisation_id);
        if (! $this->access->canExportProgram($actor, $program)) {
            throw new LogicException('Identifiable Case export requires explicit Program export access.');
        }

        $membership = $actor->organisationMembership($program->organisation);
        if ($membership === null) {
            throw new LogicException('Identifiable Case export requires an active Organisation Membership.');
        }

        $cases = ServiceCase::query()
            ->where('program_id', $program->id)
            ->where(fn ($query) => $query
                ->where('confidentiality', CaseClassification::Confidential)
                ->orWhereHas('restrictedAccessGrants', fn ($grants) => $grants
                    ->whereDoesntHave('revocation')
                    ->where(fn ($active) => $active->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->where('membership_id', $membership->id)
                    ->where('permission', RestrictedAccessPermission::SensitiveData)))
            ->with(['organisation', 'program', 'party:id,uuid,display_name'])
            ->orderBy('opened_at')
            ->get();
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new LogicException('Unable to create Case export.');
        }

        fputcsv($stream, ['case_id', 'party_uuid', 'party_name', 'status', 'classification', 'opened_at', 'closed_at']);
        foreach ($cases as $case) {
            fputcsv($stream, [
                $case->id,
                $case->party->uuid,
                $case->party->display_name,
                $case->status->value,
                $case->confidentiality->value,
                $case->opened_at->toAtomString(),
                $case->closed_at?->toAtomString(),
            ]);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        if ($contents === false) {
            throw new LogicException('Unable to read Case export.');
        }

        $this->recordAudit->handle($program->organisation, TenantAuditEventType::IdentifiableCaseExported, 'program', (string) $program->id, [
            'program_id' => $program->id,
            'record_count' => $cases->count(),
        ], $actor);

        return $contents;
    }
}
