<?php

namespace App\Console\Commands;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\CaseDocumentState;
use App\Enums\TenantAuditEventType;
use App\Models\CaseDocumentVersion;
use App\Models\Organisation;
use App\OrganisationContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('case-documents:reconcile')]
#[Description('Fail closed and remove abandoned or orphaned case-document quarantine files')]
class ReconcileCaseDocumentQuarantine extends Command
{
    public function handle(OrganisationContext $context, RecordTenantAuditEvent $recordAudit): int
    {
        $disk = Storage::disk((string) config('case_documents.quarantine_disk'));
        $expectedPaths = [];

        $context->each(function (Organisation $organisation) use ($disk, &$expectedPaths, $recordAudit): void {
            CaseDocumentVersion::query()
                ->with('document.serviceCase.organisation')
                ->whereIn('state', [
                    CaseDocumentState::AwaitingUpload,
                    CaseDocumentState::Quarantined,
                    CaseDocumentState::Scanning,
                    CaseDocumentState::ScanFailed,
                ])
                ->orderBy('created_at')
                ->each(function (CaseDocumentVersion $version) use ($disk, &$expectedPaths, $recordAudit): void {
                    if ($version->state === CaseDocumentState::AwaitingUpload && $version->created_at?->lt(now()->subHour())) {
                        $this->transition($version, CaseDocumentState::Deleted, 'abandoned', $recordAudit);

                        return;
                    }

                    if ($version->quarantine_path === null || ! $disk->exists($version->quarantine_path)) {
                        if ($version->result_category !== 'quarantine_missing') {
                            $this->transition($version, CaseDocumentState::ScanFailed, 'missing', $recordAudit);
                        }

                        return;
                    }

                    if ($version->expires_at?->isPast()) {
                        if ($version->state === CaseDocumentState::Scanning && $version->scan_started_at?->gt(now()->subMinutes(5))) {
                            $expectedPaths[] = $version->quarantine_path;

                            return;
                        }

                        if ($this->transition($version, CaseDocumentState::Deleted, 'expired', $recordAudit)) {
                            $disk->delete($version->quarantine_path);
                        }

                        return;
                    }

                    $expectedPaths[] = $version->quarantine_path;
                });
        });

        foreach (array_diff($disk->allFiles(), $expectedPaths) as $path) {
            try {
                if ($disk->lastModified($path) < now()->subHour()->getTimestamp()) {
                    $disk->delete($path);
                }
            } catch (Throwable) {
                // A concurrent worker may already have removed the opaque quarantine object.
            }
        }

        return self::SUCCESS;
    }

    private function transition(
        CaseDocumentVersion $version,
        CaseDocumentState $state,
        string $outcome,
        RecordTenantAuditEvent $recordAudit,
    ): bool {
        return DB::transaction(function () use ($outcome, $recordAudit, $state, $version): bool {
            $locked = CaseDocumentVersion::query()->lockForUpdate()->findOrFail($version->id);

            if ($locked->state !== $version->state) {
                return false;
            }

            $locked->forceFill([
                'state' => $state,
                'quarantine_path' => $state === CaseDocumentState::Deleted ? null : $locked->quarantine_path,
                'result_category' => 'quarantine_'.$outcome,
                'scanned_at' => now(),
            ])->save();
            $document = $locked->document;
            $recordAudit->handle(
                $document->serviceCase->organisation,
                TenantAuditEventType::CaseDocumentScanCompleted,
                'case_document',
                $document->id,
                ['document_id' => $document->id, 'generation' => $locked->generation, 'outcome' => $outcome],
            );

            return true;
        });
    }
}
