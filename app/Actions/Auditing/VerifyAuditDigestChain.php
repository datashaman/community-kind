<?php

namespace App\Actions\Auditing;

use App\Actions\Security\RecordPlatformSecurityEvent;
use App\Enums\PlatformSecurityEventType;
use App\Models\AuditDigestManifest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonException;
use LogicException;

class VerifyAuditDigestChain
{
    public function __construct(
        private readonly CreateDailyAuditDigest $digests,
        private readonly RecordPlatformSecurityEvent $recordSecurityEvent,
    ) {}

    /** @return array{valid: bool, failures: list<array{date: string, reason: string}>} */
    public function handle(): array
    {
        $manifests = AuditDigestManifest::query()->oldest('manifest_date')->get();
        $failures = [];
        $expectedPrevious = null;
        $expectedDate = $manifests->first()?->manifest_date->toImmutable();
        $yesterday = CarbonImmutable::now('UTC')->startOfDay()->subDay();

        if ($manifests->isEmpty()) {
            $failures[] = ['date' => $yesterday->format('Y-m-d'), 'reason' => 'missing_manifest'];
        }

        foreach ($manifests as $manifest) {
            $date = $manifest->manifest_date->toImmutable()->utc()->startOfDay();
            while ($expectedDate !== null && $expectedDate->lessThan($date)) {
                $failures[] = ['date' => $expectedDate->format('Y-m-d'), 'reason' => 'missing_manifest'];
                $expectedDate = $expectedDate->addDay();
            }

            if ($manifest->previous_manifest_digest !== $expectedPrevious) {
                $failures[] = ['date' => $date->format('Y-m-d'), 'reason' => 'chain_mismatch'];
            }

            foreach ($this->verifyManifest($manifest, $date) as $reason) {
                $failures[] = ['date' => $date->format('Y-m-d'), 'reason' => $reason];
            }

            $expectedPrevious = $manifest->manifest_digest;
            $expectedDate = $date->addDay();
        }

        while ($expectedDate !== null && $expectedDate->lessThanOrEqualTo($yesterday)) {
            $failures[] = ['date' => $expectedDate->format('Y-m-d'), 'reason' => 'missing_manifest'];
            $expectedDate = $expectedDate->addDay();
        }

        $failures = array_values(array_unique($failures, SORT_REGULAR));
        if ($failures === []) {
            foreach ($manifests as $manifest) {
                $manifest->update(['verified_at' => now()]);
            }

            return ['valid' => true, 'failures' => []];
        }

        foreach ($failures as $failure) {
            $manifest = $manifests->first(fn (AuditDigestManifest $candidate): bool => $candidate->manifest_date->format('Y-m-d') === $failure['date']);
            $metadata = [
                'manifest_date' => $failure['date'],
                'reason_code' => $failure['reason'],
                'manifest_digest' => $manifest?->manifest_digest,
            ];
            Log::channel((string) config('audit_integrity.alert_log_channel'))->critical('Audit integrity verification failed.', $metadata);
            $this->recordSecurityEvent->handle(PlatformSecurityEventType::AuditIntegrityCheckFailed, $metadata);
        }

        return ['valid' => false, 'failures' => $failures];
    }

    /** @return list<string> */
    private function verifyManifest(AuditDigestManifest $manifest, CarbonImmutable $date): array
    {
        $key = config('audit_integrity.signing_key');
        if (! is_string($key) || $key === '') {
            throw new LogicException('AUDIT_DIGEST_SIGNING_KEY must be configured.');
        }

        $failures = [];
        $disk = Storage::disk((string) config('audit_integrity.disk'));
        if (! $disk->exists($manifest->manifest_path)) {
            $failures[] = 'missing_manifest_file';
        } else {
            try {
                $storedManifest = json_decode($disk->get($manifest->manifest_path), true, flags: JSON_THROW_ON_ERROR);
                $expectedManifest = $this->diskManifest($manifest);
                if (! is_array($storedManifest) || ! hash_equals($this->digests->canonicalJson($expectedManifest), $this->digests->canonicalJson($storedManifest))) {
                    $failures[] = 'manifest_mismatch';
                }
            } catch (JsonException) {
                $failures[] = 'malformed_manifest';
            }
        }

        $calculatedDigest = hash('sha256', $this->digests->canonicalJson($this->manifestPayload($manifest)));
        if (! hash_equals($calculatedDigest, $manifest->manifest_digest)) {
            $failures[] = 'manifest_digest_mismatch';
        }

        $expectedSignature = hash_hmac('sha256', $manifest->manifest_digest, $key);
        if (! hash_equals($expectedSignature, $manifest->signature)) {
            $failures[] = 'signature_mismatch';
        }

        if (! $disk->exists($manifest->event_export_path)) {
            $failures[] = 'missing_event_export';

            return $failures;
        }

        $storedExport = $disk->get($manifest->event_export_path);
        if (! hash_equals($manifest->event_digest, hash('sha256', $storedExport))) {
            $failures[] = 'event_digest_mismatch';
        }

        $liveExport = $this->digests->exportForDate($date);
        if ($storedExport !== $liveExport['contents']) {
            $failures[] = $this->classifyEventMismatch($storedExport, $liveExport['contents']);
        }
        if ($manifest->event_count !== $liveExport['count']) {
            $failures[] = 'event_count_mismatch';
        }

        return array_values(array_unique($failures));
    }

    /** @return array<string, int|string|null> */
    private function diskManifest(AuditDigestManifest $manifest): array
    {
        return [
            'manifest_date' => $manifest->manifest_date->format('Y-m-d'),
            'event_count' => $manifest->event_count,
            'event_digest' => $manifest->event_digest,
            'previous_manifest_digest' => $manifest->previous_manifest_digest,
            'event_export_path' => $manifest->event_export_path,
            'manifest_path' => $manifest->manifest_path,
            'manifest_digest' => $manifest->manifest_digest,
            'signature' => $manifest->signature,
        ];
    }

    /** @return array<string, int|string|null> */
    private function manifestPayload(AuditDigestManifest $manifest): array
    {
        return [
            'manifest_date' => $manifest->manifest_date->format('Y-m-d'),
            'event_count' => $manifest->event_count,
            'event_digest' => $manifest->event_digest,
            'previous_manifest_digest' => $manifest->previous_manifest_digest,
            'event_export_path' => $manifest->event_export_path,
            'manifest_path' => $manifest->manifest_path,
        ];
    }

    private function classifyEventMismatch(string $stored, string $live): string
    {
        $storedLines = array_values(array_filter(explode("\n", $stored), fn (string $line): bool => $line !== ''));
        $liveLines = array_values(array_filter(explode("\n", $live), fn (string $line): bool => $line !== ''));
        if (count($storedLines) !== count($liveLines)) {
            return 'missing_or_extra_event';
        }

        $storedIds = array_map(fn (string $line): string => $this->eventIdentity($line), $storedLines);
        $liveIds = array_map(fn (string $line): string => $this->eventIdentity($line), $liveLines);
        if (collect($storedIds)->contains(fn (string $id): bool => str_starts_with($id, 'malformed:'))) {
            return 'malformed_event';
        }
        if ($storedIds !== $liveIds && $this->sameMembers($storedIds, $liveIds)) {
            return 'reordered_event';
        }
        if ($storedIds === $liveIds) {
            return 'altered_event';
        }

        return 'mismatched_event';
    }

    private function eventIdentity(string $line): string
    {
        try {
            $event = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'malformed:'.hash('sha256', $line);
        }

        return is_array($event) ? (string) ($event['stream'] ?? '').':'.(string) ($event['id'] ?? '') : '';
    }

    /** @param list<string> $left
     * @param  list<string>  $right
     */
    private function sameMembers(array $left, array $right): bool
    {
        sort($left);
        sort($right);

        return $left === $right;
    }
}
