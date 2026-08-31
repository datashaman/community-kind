<?php

namespace App\Actions\Auditing;

use App\Models\AuditDigestManifest;
use App\Models\PlatformSecurityEvent;
use App\Models\TenantAuditEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

class CreateDailyAuditDigest
{
    public function handle(CarbonImmutable $date): AuditDigestManifest
    {
        $date = $date->utc()->startOfDay();

        return Cache::lock('audit-digest:'.$date->format('Y-m-d'), 60)
            ->block(10, fn (): AuditDigestManifest => $this->create($date));
    }

    private function create(CarbonImmutable $date): AuditDigestManifest
    {
        if ($date->greaterThanOrEqualTo(CarbonImmutable::now('UTC')->startOfDay())) {
            throw new LogicException('Audit digests can only close completed UTC days.');
        }

        $existing = AuditDigestManifest::query()->whereDate('manifest_date', $date)->first();
        if ($existing !== null) {
            return $existing;
        }

        $key = $this->signingKey();
        $export = $this->exportForDate($date);
        $previousDigest = AuditDigestManifest::query()
            ->whereDate('manifest_date', '<', $date)
            ->latest('manifest_date')
            ->value('manifest_digest');
        $directory = 'audit/'.$date->format('Y-m-d');
        $eventPath = $directory.'/events.jsonl';
        $manifestPath = $directory.'/manifest.json';
        $payload = [
            'manifest_date' => $date->format('Y-m-d'),
            'event_count' => $export['count'],
            'event_digest' => hash('sha256', $export['contents']),
            'previous_manifest_digest' => is_string($previousDigest) ? $previousDigest : null,
            'event_export_path' => $eventPath,
            'manifest_path' => $manifestPath,
        ];
        $manifestDigest = hash('sha256', $this->canonicalJson($payload));
        $diskManifest = $payload + [
            'manifest_digest' => $manifestDigest,
            'signature' => hash_hmac('sha256', $manifestDigest, $key),
        ];
        $disk = Storage::disk((string) config('audit_integrity.disk'));

        try {
            $disk->put($eventPath, $export['contents']);
            $disk->put($manifestPath, $this->canonicalJson($diskManifest)."\n");

            return AuditDigestManifest::query()->create([
                ...$diskManifest,
            ]);
        } catch (Throwable $throwable) {
            $disk->delete([$eventPath, $manifestPath]);

            throw $throwable;
        }
    }

    /** @return array{contents: string, count: int, ids: list<string>} */
    public function exportForDate(CarbonImmutable $date): array
    {
        $start = $date->utc()->startOfDay();
        $end = $start->addDay();
        $events = [];

        foreach (TenantAuditEvent::withoutGlobalScopes()->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)->get() as $event) {
            $events[] = [
                'stream' => 'tenant',
                'id' => $event->id,
                'organisation_id' => $event->organisation_id,
                'type' => $event->type->value,
                'schema_version' => $event->schema_version,
                'actor_user_id' => $event->actor_user_id,
                'subject_type' => $event->subject_type,
                'subject_id' => $event->subject_id,
                'payload' => $event->payload,
                'occurred_at' => $event->occurred_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'recorded_at' => (string) $event->getRawOriginal('created_at'),
            ];
        }

        foreach (PlatformSecurityEvent::query()->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)->get() as $event) {
            $events[] = [
                'stream' => 'platform',
                'id' => $event->id,
                'type' => $event->type->value,
                'schema_version' => $event->schema_version,
                'incident_uuid' => $event->getAttribute('incident_uuid'),
                'actor_user_id' => $event->actor_user_id,
                'subject_user_id' => $event->subject_user_id,
                'metadata' => $event->metadata,
                'occurred_at' => $event->occurred_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'recorded_at' => (string) $event->getRawOriginal('created_at'),
            ];
        }

        usort($events, fn (array $left, array $right): int => [$left['occurred_at'], $left['stream'], $left['id']] <=> [$right['occurred_at'], $right['stream'], $right['id']]);
        $lines = array_map(fn (array $event): string => $this->canonicalJson($event), $events);

        return [
            'contents' => $lines === [] ? '' : implode("\n", $lines)."\n",
            'count' => count($events),
            'ids' => array_map(fn (array $event): string => $event['stream'].':'.$event['id'], $events),
        ];
    }

    /** @param array<array-key, mixed> $value */
    public function canonicalJson(array $value): string
    {
        $value = $this->canonicalize($value);

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private function canonicalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }

    private function signingKey(): string
    {
        $key = config('audit_integrity.signing_key');
        if (! is_string($key) || $key === '') {
            throw new LogicException('AUDIT_DIGEST_SIGNING_KEY must be configured.');
        }

        return $key;
    }
}
