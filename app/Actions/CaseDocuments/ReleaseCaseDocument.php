<?php

namespace App\Actions\CaseDocuments;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Contracts\MalwareScanner;
use App\Data\CaseDocuments\MalwareScanResult;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseDocumentState;
use App\Enums\MalwareScanVerdict;
use App\Enums\TenantAuditEventType;
use App\Exceptions\DocumentPolicyRejected;
use App\Models\CaseDocument;
use App\Models\CaseDocumentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ReleaseCaseDocument
{
    public function __construct(
        private MalwareScanner $scanner,
        private RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(CaseDocument $document, int $expectedGeneration): void
    {
        $document = CaseDocument::query()->findOrFail($document->id);

        if ($document->generation !== $expectedGeneration) {
            return;
        }

        $version = $document->versions()->where('generation', $expectedGeneration)->firstOrFail();

        if (! in_array($version->state, [CaseDocumentState::Quarantined, CaseDocumentState::ScanFailed], true)) {
            return;
        }

        $quarantine = Storage::disk((string) config('case_documents.quarantine_disk'));

        if ($version->quarantine_path === null || ! $quarantine->exists($version->quarantine_path)) {
            DB::transaction(function () use ($document, $version): void {
                $version->forceFill(['state' => CaseDocumentState::ScanFailed, 'result_category' => 'quarantine_missing'])->save();
                $this->auditScan($document, $version, 'missing');
            });

            return;
        }

        if ($version->expires_at?->isPast()) {
            $quarantine->delete($version->quarantine_path);
            DB::transaction(function () use ($document, $version): void {
                $version->forceFill([
                    'state' => CaseDocumentState::Deleted,
                    'quarantine_path' => null,
                    'result_category' => 'scan_expired',
                    'scanned_at' => now(),
                ])->save();
                $this->auditScan($document, $version, 'expired');
            });

            return;
        }

        $claimed = DB::transaction(function () use ($document, $expectedGeneration, $version): bool {
            $lockedDocument = CaseDocument::query()->lockForUpdate()->findOrFail($document->id);
            $lockedVersion = CaseDocumentVersion::query()->lockForUpdate()->findOrFail($version->id);

            if ($lockedDocument->generation !== $expectedGeneration
                || ! in_array($lockedVersion->state, [CaseDocumentState::Quarantined, CaseDocumentState::ScanFailed], true)) {
                return false;
            }

            $lockedVersion->forceFill(['state' => CaseDocumentState::Scanning, 'scan_started_at' => now()])->save();

            return true;
        });

        if (! $claimed) {
            return;
        }

        $version->refresh();

        try {
            if (! $this->scanner->isHealthy()) {
                throw new RuntimeException('The malware scanner is unavailable or stale.');
            }

            $firstResult = $this->scan($quarantine->readStream($version->quarantine_path));

            if ($firstResult->verdict !== MalwareScanVerdict::Clean) {
                $this->finishNonClean($document, $version, $firstResult);

                return;
            }

            $bytes = $quarantine->get($version->quarantine_path);
            try {
                $releaseBytes = $this->sanitizeImage($bytes, (string) $version->detected_mime);
            } catch (DocumentPolicyRejected) {
                $this->finishNonClean($document, $version, new MalwareScanResult(
                    MalwareScanVerdict::Rejected,
                    $firstResult->engineVersion,
                    $firstResult->signatureVersion,
                    'policy_rejected',
                ));

                return;
            }

            if ($releaseBytes !== $bytes) {
                $stream = fopen('php://temp', 'w+b');

                if ($stream === false) {
                    throw new RuntimeException('Unable to prepare the sanitized document.');
                }

                fwrite($stream, $releaseBytes);
                rewind($stream);
                $secondResult = $this->scanner->scan($stream);
                fclose($stream);

                if ($secondResult->verdict !== MalwareScanVerdict::Clean) {
                    $this->finishNonClean($document, $version, $secondResult);

                    return;
                }

                $firstResult = $secondResult;
            }

            $this->release($document, $version, $releaseBytes, $firstResult);
        } catch (Throwable $exception) {
            $version->refresh()->forceFill(['state' => CaseDocumentState::ScanFailed, 'result_category' => 'scan_failed'])->save();

            throw $exception;
        }
    }

    /** @param resource|false $stream */
    private function scan(mixed $stream): MalwareScanResult
    {
        if ($stream === false) {
            throw new RuntimeException('Unable to read the quarantined document.');
        }

        try {
            return $this->scanner->scan($stream);
        } finally {
            fclose($stream);
        }
    }

    private function finishNonClean(CaseDocument $document, CaseDocumentVersion $version, MalwareScanResult $result): void
    {
        if ($result->verdict === MalwareScanVerdict::Rejected && $version->quarantine_path !== null) {
            Storage::disk((string) config('case_documents.quarantine_disk'))->delete($version->quarantine_path);
        }

        DB::transaction(function () use ($document, $result, $version): void {
            $version->forceFill([
                'state' => $result->verdict === MalwareScanVerdict::Rejected ? CaseDocumentState::Rejected : CaseDocumentState::ScanFailed,
                'quarantine_path' => $result->verdict === MalwareScanVerdict::Rejected ? null : $version->quarantine_path,
                'scanner_engine_version' => $result->engineVersion,
                'scanner_signature_version' => $result->signatureVersion,
                'result_category' => $result->reasonCategory,
                'scanned_at' => now(),
            ])->save();
            $this->auditScan($document, $version, $result->verdict->value);
        });

        if ($result->verdict === MalwareScanVerdict::Failed) {
            throw new RuntimeException('The document scan failed and will be retried.');
        }
    }

    private function release(CaseDocument $document, CaseDocumentVersion $version, string $bytes, MalwareScanResult $result): void
    {
        $released = Storage::disk((string) config('case_documents.released_disk'));
        $objectKey = app()->environment().'/'.$document->serviceCase->organisation->uuid.'/'.$document->id.'/'.$version->id;
        $checksum = hash('sha256', $bytes);

        $released->put($objectKey, $bytes, ['visibility' => 'private']);
        $storedStream = $released->readStream($objectKey);

        if ($storedStream === null) {
            $released->delete($objectKey);
            throw new RuntimeException('Unable to verify the released document.');
        }

        $storedChecksum = hash_init('sha256');
        hash_update_stream($storedChecksum, $storedStream);
        fclose($storedStream);

        if ($released->size($objectKey) !== strlen($bytes) || hash_final($storedChecksum) !== $checksum) {
            $released->delete($objectKey);
            throw new RuntimeException('The released document failed integrity verification.');
        }

        try {
            DB::transaction(function () use ($bytes, $checksum, $document, $objectKey, $result, $version): void {
                $lockedDocument = CaseDocument::query()->lockForUpdate()->findOrFail($document->id);
                $lockedVersion = CaseDocumentVersion::query()->lockForUpdate()->findOrFail($version->id);

                if ($lockedDocument->generation !== $version->generation || $lockedVersion->state !== CaseDocumentState::Scanning) {
                    throw new RuntimeException('The document generation is stale.');
                }

                $lockedVersion->forceFill([
                    'state' => CaseDocumentState::Clean,
                    'quarantine_path' => null,
                    'object_key' => $objectKey,
                    'byte_size' => strlen($bytes),
                    'encrypted_sha256' => new ClassifiedValue($checksum),
                    'scanner_engine_version' => $result->engineVersion,
                    'scanner_signature_version' => $result->signatureVersion,
                    'result_category' => 'clean',
                    'scanned_at' => now(),
                ])->save();
                $lockedDocument->forceFill([
                    'current_version_id' => $lockedVersion->id,
                    'classification' => $lockedVersion->classification,
                ]);
                $lockedDocument->encrypted_display_name = new ClassifiedValue($lockedVersion->encrypted_display_name->reveal());
                $lockedDocument->save();
                $this->auditScan($lockedDocument, $lockedVersion, 'clean');
            });
        } catch (Throwable $exception) {
            $released->delete($objectKey);

            throw $exception;
        }

        if ($version->quarantine_path !== null) {
            Storage::disk((string) config('case_documents.quarantine_disk'))->delete($version->quarantine_path);
        }
    }

    private function sanitizeImage(string $bytes, string $mime): string
    {
        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return $bytes;
        }

        $dimensions = @getimagesizefromstring($bytes);

        if ($dimensions === false || ($dimensions[0] * $dimensions[1]) > (int) config('case_documents.max_image_pixels')) {
            throw new DocumentPolicyRejected('The image dimensions are invalid.');
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new DocumentPolicyRejected('The image could not be decoded.');
        }

        ob_start();
        $encoded = $mime === 'image/jpeg' ? imagejpeg($image, null, 90) : imagepng($image, null, 6);
        $sanitized = ob_get_clean();
        imagedestroy($image);

        if (! $encoded || ! is_string($sanitized)) {
            throw new DocumentPolicyRejected('The image could not be sanitized.');
        }

        return $sanitized;
    }

    private function auditScan(CaseDocument $document, CaseDocumentVersion $version, string $outcome): void
    {
        $this->recordAudit->handle(
            $document->serviceCase->organisation,
            TenantAuditEventType::CaseDocumentScanCompleted,
            'case_document',
            $document->id,
            ['document_id' => $document->id, 'generation' => $version->generation, 'outcome' => $outcome],
        );
    }
}
