<?php

use App\Actions\Auditing\CreateDailyAuditDigest;
use App\Enums\PlatformSecurityEventType;
use App\Enums\SecurityIncidentEntryType;
use App\Enums\SecurityIncidentStatus;
use App\Models\PlatformSecurityEvent;
use App\Models\SecurityIncident;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

it('exercises every synthetic compromise scenario and preserves a signed redacted evidence pack', function () {
    Storage::fake('incident-exercise-test');
    config([
        'audit_integrity.disk' => 'incident-exercise-test',
        'audit_integrity.signing_key' => 'test-only-independent-incident-signing-key',
    ]);
    $actor = User::factory()->create([
        'name' => 'Secret Responder Name',
        'email' => 'secret-responder@example.test',
    ]);

    expect(Artisan::call('security:incident:exercise', ['--actor-user' => $actor->id]))->toBe(0)
        ->and(SecurityIncident::query()->count())->toBe(5)
        ->and(SecurityIncident::query()->where('status', SecurityIncidentStatus::Closed)->count())->toBe(5);

    $incidents = SecurityIncident::query()->with('entries')->get();
    foreach ($incidents as $incident) {
        expect($incident->entries)->toHaveCount(13)
            ->and($incident->entries->pluck('type'))->toContain(
                SecurityIncidentEntryType::Decision,
                SecurityIncidentEntryType::Action,
                SecurityIncidentEntryType::EvidenceReference,
                SecurityIncidentEntryType::RecoveryGate,
                SecurityIncidentEntryType::CorrectiveAction,
                SecurityIncidentEntryType::StatusChange,
            );
    }

    $completion = PlatformSecurityEvent::query()->where('type', PlatformSecurityEventType::IncidentExerciseCompleted)->sole();
    $path = 'incident-exercises/'.$completion->metadata['exercise_id'].'/evidence-pack.json';
    Storage::disk('incident-exercise-test')->assertExists($path);
    $contents = Storage::disk('incident-exercise-test')->get($path);
    $pack = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    $signedPayload = $pack;
    unset($signedPayload['pack_digest'], $signedPayload['signature']);
    expect($pack['scenarios'])->toHaveCount(5)
        ->and(collect($pack['scenarios'])->pluck('scenario')->all())->toBe([
            'cross_tenant_exposure',
            'privileged_account_compromise',
            'malicious_file_release',
            'key_disclosure',
            'audit_backup_integrity_failure',
        ])
        ->and($pack['fictional_data'])->toBeTrue()
        ->and($pack['access_mode'])->toBe('synthetic_metadata_only_no_break_glass')
        ->and($pack['pack_digest'])->toBe(hash('sha256', app(CreateDailyAuditDigest::class)->canonicalJson($signedPayload)))
        ->and($pack['signature'])->toBe(hash_hmac('sha256', $pack['pack_digest'], config('audit_integrity.signing_key')))
        ->and($contents)->not->toContain($actor->name)
        ->not->toContain($actor->email)
        ->not->toContain('case note')
        ->not->toContain('credential value');
    expect(collect($pack['scenarios'])->every(fn (array $scenario): bool => isset(
        $scenario['containment_decision'],
        $scenario['communications_decision'],
        $scenario['evidence_chain']['original_digest'],
        $scenario['evidence_chain']['working_copy_digest'],
        $scenario['evidence_chain']['storage_location'],
        $scenario['evidence_chain']['custody_log'][0]['occurred_at'],
        $scenario['recovery_gate']['status'],
        $scenario['corrective_action']['due_at'],
    )))->toBeTrue();
});
