<?php

use App\Actions\Auditing\CreateDailyAuditDigest;
use App\Actions\Auditing\VerifyAuditDigestChain;
use App\Enums\PlatformSecurityEventType;
use App\Models\AuditDigestManifest;
use App\Models\Organisation;
use App\Models\PlatformSecurityEvent;
use App\Models\TenantAuditEvent;
use App\OrganisationContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-06-03 12:00:00 UTC');
    Storage::fake('audit-recovery-test');
    config([
        'audit_integrity.disk' => 'audit-recovery-test',
        'audit_integrity.signing_key' => 'test-only-independent-audit-signing-key',
        'audit_integrity.alert_log_channel' => 'stack',
    ]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('creates deterministic chained manifests and verifies the recovery set against live events', function () {
    createAuditEventsFor('2026-06-01');
    createAuditEventsFor('2026-06-02');
    $create = app(CreateDailyAuditDigest::class);
    $first = $create->handle(CarbonImmutable::parse('2026-06-01', 'UTC'));
    $second = $create->handle(CarbonImmutable::parse('2026-06-02', 'UTC'));

    expect($first->event_count)->toBe(2)
        ->and($second->previous_manifest_digest)->toBe($first->manifest_digest)
        ->and($second->signature)->toBe(hash_hmac('sha256', $second->manifest_digest, config('audit_integrity.signing_key')))
        ->and(app(VerifyAuditDigestChain::class)->handle())->toBe(['valid' => true, 'failures' => []])
        ->and(AuditDigestManifest::query()->whereNotNull('verified_at')->count())->toBe(2);
    Storage::disk('audit-recovery-test')->assertExists($first->event_export_path);
    Storage::disk('audit-recovery-test')->assertExists($second->manifest_path);
});

it('detects missing, reordered, altered, and mismatched recovery events and alerts without event content', function (string $mutation, string $reason) {
    createAuditEventsFor('2026-06-02');
    $manifest = app(CreateDailyAuditDigest::class)->handle(CarbonImmutable::parse('2026-06-02', 'UTC'));
    $disk = Storage::disk('audit-recovery-test');
    $lines = array_values(array_filter(explode("\n", $disk->get($manifest->event_export_path))));

    $contents = match ($mutation) {
        'missing' => $lines[0]."\n",
        'reordered' => implode("\n", array_reverse($lines))."\n",
        'altered' => str_replace('other_browser_sessions_revoked', 'altered_event_type', implode("\n", $lines))."\n",
        'mismatched' => preg_replace('/"id":"[^"]+"/', '"id":"mismatched-id"', implode("\n", $lines), 1)."\n",
        'malformed' => $lines[0]."\n{not-json}\n",
    };
    $disk->put($manifest->event_export_path, $contents);

    $result = app(VerifyAuditDigestChain::class)->handle();
    expect($result['valid'])->toBeFalse()
        ->and(collect($result['failures'])->pluck('reason'))->toContain($reason);
    $alert = PlatformSecurityEvent::query()->where('type', PlatformSecurityEventType::AuditIntegrityCheckFailed)->latest('occurred_at')->firstOrFail();
    expect($alert->metadata)->toHaveKeys(['manifest_date', 'reason_code', 'manifest_digest'])
        ->and(json_encode($alert->metadata))->not->toContain('altered_event_type')
        ->not->toContain('mismatched-id');
})->with([
    'missing event' => ['missing', 'missing_or_extra_event'],
    'reordered events' => ['reordered', 'reordered_event'],
    'altered event' => ['altered', 'altered_event'],
    'mismatched event identity' => ['mismatched', 'mismatched_event'],
    'malformed event export' => ['malformed', 'malformed_event'],
]);

it('alerts when no closed-day manifest exists', function () {
    $result = app(VerifyAuditDigestChain::class)->handle();

    expect($result)->toBe([
        'valid' => false,
        'failures' => [['date' => '2026-06-02', 'reason' => 'missing_manifest']],
    ])->and(PlatformSecurityEvent::query()->where('type', PlatformSecurityEventType::AuditIntegrityCheckFailed)->count())->toBe(1);
});

it('detects missing manifests and broken chain links', function () {
    CarbonImmutable::setTestNow('2026-06-04 12:00:00 UTC');
    createAuditEventsFor('2026-06-01');
    createAuditEventsFor('2026-06-03');
    $create = app(CreateDailyAuditDigest::class);
    $create->handle(CarbonImmutable::parse('2026-06-01', 'UTC'));
    $third = $create->handle(CarbonImmutable::parse('2026-06-03', 'UTC'));
    DB::table('audit_digest_manifests')->where('id', $third->id)->update(['previous_manifest_digest' => str_repeat('f', 64)]);

    $failures = collect(app(VerifyAuditDigestChain::class)->handle()['failures']);
    expect($failures)->toContain(['date' => '2026-06-02', 'reason' => 'missing_manifest'])
        ->toContain(['date' => '2026-06-03', 'reason' => 'chain_mismatch'])
        ->toContain(['date' => '2026-06-03', 'reason' => 'manifest_digest_mismatch']);
});

function createAuditEventsFor(string $date): void
{
    $organisation = Organisation::factory()->active()->create();
    app(OrganisationContext::class)->run($organisation, fn () => TenantAuditEvent::factory()->for($organisation)->create([
        'occurred_at' => $date.' 10:00:00',
    ]));
    PlatformSecurityEvent::factory()->create(['occurred_at' => $date.' 11:00:00']);
}
