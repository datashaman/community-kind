<?php

namespace App\Actions\CaseDocuments;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Authorization\CaseAccess;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseClassification;
use App\Enums\CaseDocumentState;
use App\Enums\TenantAuditEventType;
use App\Jobs\ScanCaseDocument;
use App\Models\CaseDocument;
use App\Models\CaseDocumentVersion;
use App\Models\ServiceCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class QuarantineCaseDocument
{
    public function __construct(
        private RecordTenantAuditEvent $recordAudit,
        private CaseAccess $caseAccess,
    ) {}

    public function handle(
        ServiceCase $case,
        UploadedFile $upload,
        CaseClassification $classification,
        User $actor,
        ?CaseDocument $document = null,
    ): CaseDocument {
        if ($case->confidentiality === CaseClassification::HighlyRestricted
            || $document?->classification === CaseClassification::HighlyRestricted) {
            $classification = CaseClassification::HighlyRestricted;
        }

        [$extension, $detectedMime] = $this->validateFile($upload);
        $disk = Storage::disk((string) config('case_documents.quarantine_disk'));
        $versionId = Str::uuid()->toString();
        $path = "{$case->organisation->uuid}/{$versionId}.upload";

        try {
            $document = DB::transaction(function () use ($actor, $case, $classification, $detectedMime, $disk, $document, $extension, $path, $upload, $versionId): CaseDocument {
                $case = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
                abort_unless($this->caseAccess->canView($actor, $case) && ! $case->status->isTerminal(), 403);
                $isReplacement = $document !== null;

                if ($document === null) {
                    $document = new CaseDocument;
                    $document->forceFill([
                        'id' => Str::uuid()->toString(),
                        'organisation_id' => $case->organisation_id,
                        'service_case_id' => $case->id,
                        'type' => 'case_document_name',
                        'classification' => $classification,
                        'generation' => 0,
                        'created_by_user_id' => $actor->id,
                    ]);
                    $document->encrypted_display_name = new ClassifiedValue($this->safeDisplayName($upload, $extension));
                    $document->save();
                } else {
                    $document = CaseDocument::query()->lockForUpdate()->findOrFail($document->id);

                    if ($document->service_case_id !== $case->id) {
                        abort(404);
                    }
                }

                if ($case->confidentiality === CaseClassification::HighlyRestricted
                    || $document->classification === CaseClassification::HighlyRestricted) {
                    $classification = CaseClassification::HighlyRestricted;
                }

                abort_if($classification === CaseClassification::HighlyRestricted && ! $this->caseAccess->canViewSensitive($actor, $case), 403);

                $document->generation++;
                $document->save();

                $version = new CaseDocumentVersion;
                $version->forceFill([
                    'id' => $versionId,
                    'organisation_id' => $case->organisation_id,
                    'case_document_id' => $document->id,
                    'type' => 'case_document_version_security',
                    'generation' => $document->generation,
                    'classification' => $classification,
                    'state' => CaseDocumentState::AwaitingUpload,
                    'detected_mime' => $detectedMime,
                    'expires_at' => now()->addDay(),
                ]);
                $version->encrypted_display_name = new ClassifiedValue($this->safeDisplayName($upload, $extension));
                $version->save();

                $stream = fopen($upload->getRealPath(), 'rb');

                if ($stream === false) {
                    throw ValidationException::withMessages(['document' => __('The document could not be quarantined.')]);
                }

                try {
                    $stored = $disk->put($path, $stream);
                } finally {
                    fclose($stream);
                }

                if (! $stored) {
                    throw ValidationException::withMessages(['document' => __('The document could not be quarantined.')]);
                }

                $version->forceFill(['state' => CaseDocumentState::Quarantined, 'quarantine_path' => $path])->save();

                $this->recordAudit->handle(
                    $case->organisation,
                    $isReplacement ? TenantAuditEventType::CaseDocumentReplaced : TenantAuditEventType::CaseDocumentUploaded,
                    'case_document',
                    $document->id,
                    [
                        'case_id' => $case->id,
                        'document_id' => $document->id,
                        'generation' => $document->generation,
                        'classification' => $classification->value,
                    ],
                    $actor,
                );

                ScanCaseDocument::dispatch($case->organisation->uuid, $document->id, $document->generation)->afterCommit();

                return $document;
            });
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        return $document;
    }

    /** @return array{string, string} */
    private function validateFile(UploadedFile $upload): array
    {
        $name = $upload->getClientOriginalName();

        if ($name === '' || basename(str_replace('\\', '/', $name)) !== $name || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
            throw ValidationException::withMessages(['document' => __('The document name is not allowed.')]);
        }

        $extension = strtolower($upload->getClientOriginalExtension());
        $stem = substr($name, 0, -(strlen($extension) + 1));

        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true) || str_contains($stem, '.')) {
            throw ValidationException::withMessages(['document' => __('Only PDF, JPEG, and PNG documents with a single extension are allowed.')]);
        }

        $allowedMimes = match ($extension) {
            'pdf' => ['application/pdf'],
            'jpg', 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
        };
        $clientMime = strtolower($upload->getClientMimeType());
        $detectedMime = strtolower((string) $upload->getMimeType());
        $header = file_get_contents($upload->getRealPath(), false, null, 0, 8);
        $tail = $extension === 'pdf'
            ? file_get_contents($upload->getRealPath(), false, null, max(0, $upload->getSize() - 1024), 1024)
            : null;
        $signatureMatches = is_string($header) && match ($extension) {
            'pdf' => str_starts_with($header, '%PDF-') && is_string($tail) && str_contains($tail, '%%EOF'),
            'jpg', 'jpeg' => str_starts_with($header, "\xFF\xD8\xFF"),
            'png' => str_starts_with($header, "\x89PNG\r\n\x1A\n"),
        };

        if (! in_array($clientMime, $allowedMimes, true) || ! in_array($detectedMime, $allowedMimes, true) || ! $signatureMatches) {
            throw ValidationException::withMessages(['document' => __('The document content does not match its allowed type.')]);
        }

        return [$extension === 'jpeg' ? 'jpg' : $extension, $detectedMime];
    }

    private function safeDisplayName(UploadedFile $upload, string $extension): string
    {
        $stem = pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME);
        $stem = trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $stem) ?? 'document');

        return mb_substr($stem !== '' ? $stem : 'document', 0, 100).'.'.$extension;
    }
}
