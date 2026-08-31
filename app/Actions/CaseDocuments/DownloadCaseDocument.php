<?php

namespace App\Actions\CaseDocuments;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Authorization\CaseAccess;
use App\Enums\CaseDocumentState;
use App\Enums\TenantAuditEventType;
use App\Models\CaseDocument;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadCaseDocument
{
    public function __construct(
        private CaseAccess $access,
        private RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(CaseDocument $document, User $actor): StreamedResponse
    {
        $document->load(['serviceCase.organisation', 'currentVersion']);

        abort_unless($this->access->canViewDocument($actor, $document), 403);
        $version = $document->currentVersion;
        abort_if($version === null || $version->state !== CaseDocumentState::Clean || $version->object_key === null, 404);

        $disk = Storage::disk((string) config('case_documents.released_disk'));
        abort_unless($disk->exists($version->object_key), 404);

        $extension = match ($version->detected_mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => abort(404),
        };

        $this->recordAudit->handle(
            $document->serviceCase->organisation,
            TenantAuditEventType::CaseDocumentDownloaded,
            'case_document',
            $document->id,
            [
                'case_id' => $document->service_case_id,
                'document_id' => $document->id,
                'generation' => $version->generation,
                'classification' => $document->classification->value,
            ],
            $actor,
        );

        $filename = 'case-document-'.substr($document->id, 0, 8).'.'.$extension;

        return response()->streamDownload(
            fn () => $this->stream($disk, $version->object_key),
            $filename,
            [
                'Content-Type' => $version->detected_mime,
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
            ],
        );
    }

    private function stream(Filesystem $disk, string $path): void
    {
        $stream = $disk->readStream($path);
        abort_if($stream === null, 404);

        try {
            fpassthru($stream);
        } finally {
            fclose($stream);
        }
    }
}
