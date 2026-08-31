<?php

use App\Actions\Security\RecordSecurityIncidentEntry;
use App\Actions\Security\ReportSecurityIncident;
use App\Enums\IncidentReasonCode;
use App\Enums\InstallationCapability;
use App\Enums\SecurityIncidentClassification;
use App\Enums\SecurityIncidentEntryType;
use App\Enums\SecurityIncidentSeverity;
use App\Enums\SecurityIncidentStatus;
use App\Http\Middleware\EnsureInstallationAccess;
use App\Models\InstallationControl;
use App\Models\Organisation;
use App\Models\OrganisationAccessHold;
use App\Models\PlatformSecurityEvent;
use App\Models\SecurityIncident;
use App\Models\SecurityIncidentEntry;
use App\Models\User;
use App\Queue\Middleware\PauseForInstallationControl;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('keeps alerts distinct from incidents and records the restricted response register', function () {
    $actor = User::factory()->create();
    $report = app(ReportSecurityIncident::class);
    $alert = $report->handle(
        SecurityIncidentClassification::Alert,
        SecurityIncidentSeverity::S4Low,
        'control_monitor',
        'Synthetic blocked attack.',
        $actor,
    );
    $incident = $report->handle(
        SecurityIncidentClassification::Incident,
        SecurityIncidentSeverity::S2High,
        'synthetic_exercise',
        'Synthetic privileged-account compromise.',
        $actor,
    );
    $entries = app(RecordSecurityIncidentEntry::class);

    foreach (SecurityIncidentEntryType::cases() as $type) {
        $entries->handle(
            $incident,
            $type,
            "Synthetic {$type->value} entry.",
            $actor,
            reference: $type === SecurityIncidentEntryType::EvidenceReference ? 'evidence:synthetic-001' : null,
            status: in_array($type, [SecurityIncidentEntryType::Action, SecurityIncidentEntryType::RecoveryGate, SecurityIncidentEntryType::CorrectiveAction], true) ? 'open' : null,
            dueAt: $type === SecurityIncidentEntryType::CorrectiveAction ? now()->addDay() : null,
        );
    }

    expect($alert->classification)->toBe(SecurityIncidentClassification::Alert)
        ->and($incident->classification)->toBe(SecurityIncidentClassification::Incident)
        ->and($incident->entries)->toHaveCount(count(SecurityIncidentEntryType::cases()));
    $this->assertDatabaseHas('platform_security_events', [
        'incident_uuid' => $incident->id,
        'type' => 'incident_reported',
    ]);
});

it('guards containment and atomically revokes access holds tenants and pauses installation capabilities', function () {
    config(['session.driver' => 'database']);
    $actor = User::factory()->create();
    $target = User::factory()->create(['remember_token' => 'remember-me']);
    $organisation = Organisation::factory()->active()->create(['name' => 'HarbourKind Private']);
    $incident = app(ReportSecurityIncident::class)->handle(
        SecurityIncidentClassification::Incident,
        SecurityIncidentSeverity::S1Critical,
        'synthetic_exercise',
        'Synthetic cross-tenant exposure.',
        $actor,
    );
    DB::table('sessions')->insert([
        'id' => 'compromised-session',
        'user_id' => $target->id,
        'payload' => '',
        'last_activity' => time(),
    ]);
    DB::table('password_reset_tokens')->insert([
        'email' => $target->email,
        'token' => 'hashed-token',
        'created_at' => now(),
    ]);

    expect(Artisan::call('security:incident:contain', [
        'incident' => $incident->id,
        '--confirm' => $incident->id,
        '--actor-user' => $actor->id,
        '--reason-code' => IncidentReasonCode::SyntheticExercise->value,
        '--revoke-user' => [$target->id],
        '--hold-organisation' => [$organisation->uuid],
        '--pause' => [
            InstallationCapability::Queues->value,
            InstallationCapability::Outbox->value,
            InstallationCapability::Uploads->value,
            InstallationCapability::Forms->value,
        ],
        '--freeze-writes' => true,
        '--credential' => ['classified-data-key-v2'],
    ]))->toBe(0);

    expect($incident->fresh()->status)->toBe(SecurityIncidentStatus::Contained)
        ->and($target->fresh()->remember_token)->toBeNull()
        ->and(InstallationControl::isPaused(InstallationCapability::Writes))->toBeTrue()
        ->and(InstallationControl::query()->whereNull('released_at')->count())->toBe(5)
        ->and(OrganisationAccessHold::query()->where('incident_uuid', $incident->id)->whereNull('released_at')->count())->toBe(1);
    $this->assertDatabaseMissing('sessions', ['id' => 'compromised-session']);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
    $this->actingAs($actor)->post(route('organisations.store'))->assertServiceUnavailable();
    $this->post('/logout')->assertRedirect();
    $this->post('/login')->assertRedirect()->assertSessionHasErrors(['email', 'password']);

    $metadata = PlatformSecurityEvent::query()
        ->where('incident_uuid', $incident->id)
        ->get()
        ->pluck('metadata')
        ->toJson();
    expect($metadata)->not->toContain('HarbourKind Private')
        ->not->toContain('Synthetic cross-tenant exposure')
        ->not->toContain('remember-me')
        ->not->toContain('hashed-token');
});

it('shuts down designated public forms without disabling authentication', function () {
    InstallationControl::factory()->create(['capability' => InstallationCapability::Forms]);
    Route::post('/synthetic-public-form', fn () => response()->noContent())
        ->middleware(['web', EnsureInstallationAccess::class.':forms'])
        ->name('forms.synthetic.store');

    $this->post('/synthetic-public-form')->assertServiceUnavailable();
    $this->post('/login')->assertRedirect()->assertSessionHasErrors(['email', 'password']);
});

it('retains queued work while an incident queue pause is active', function () {
    InstallationControl::factory()->create(['capability' => InstallationCapability::Queues]);
    $job = new class
    {
        public ?int $releasedFor = null;

        public function release(int $delay): void
        {
            $this->releasedFor = $delay;
        }
    };
    $continued = false;

    (new PauseForInstallationControl)->handle($job, function () use (&$continued): void {
        $continued = true;
    });

    expect($job->releasedFor)->toBe(60)
        ->and($continued)->toBeFalse();
});

it('requires exact incident confirmation and leaves state unchanged when authorization fails', function () {
    $actor = User::factory()->create();
    $incident = SecurityIncident::factory()->create();

    expect(Artisan::call('security:incident:contain', [
        'incident' => $incident->id,
        '--confirm' => 'wrong-incident',
        '--actor-user' => $actor->id,
        '--reason-code' => IncidentReasonCode::SyntheticExercise->value,
        '--freeze-writes' => true,
    ]))->toBe(1)
        ->and(InstallationControl::query()->count())->toBe(0)
        ->and($incident->fresh()->status)->toBe(SecurityIncidentStatus::Reported);
});

it('rejects missing containment targets instead of silently applying a partial response', function () {
    $actor = User::factory()->create();
    $incident = SecurityIncident::factory()->create();

    expect(Artisan::call('security:incident:contain', [
        'incident' => $incident->id,
        '--confirm' => $incident->id,
        '--actor-user' => $actor->id,
        '--reason-code' => IncidentReasonCode::SyntheticExercise->value,
        '--revoke-user' => ['999999'],
    ]))->toBe(2)
        ->and(InstallationControl::query()->count())->toBe(0)
        ->and($incident->fresh()->status)->toBe(SecurityIncidentStatus::Reported);
});

it('is idempotent for active hold and capability targets while allowing containment expansion', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $incident = SecurityIncident::factory()->create();
    $options = [
        'incident' => $incident->id,
        '--confirm' => $incident->id,
        '--actor-user' => $actor->id,
        '--reason-code' => IncidentReasonCode::SyntheticExercise->value,
        '--hold-organisation' => [$organisation->uuid],
        '--hold-level' => 'read_only',
        '--pause' => [InstallationCapability::Queues->value],
    ];

    expect(Artisan::call('security:incident:contain', $options))->toBe(0)
        ->and(Artisan::call('security:incident:contain', $options))->toBe(0)
        ->and(OrganisationAccessHold::query()->where('incident_uuid', $incident->id)->count())->toBe(1)
        ->and(InstallationControl::query()->where('incident_uuid', $incident->id)->count())->toBe(1)
        ->and(PlatformSecurityEvent::query()
            ->where('incident_uuid', $incident->id)
            ->where('type', 'incident_status_changed')
            ->count())->toBe(1);

    expect(Artisan::call('security:incident:contain', [
        ...$options,
        '--hold-level' => 'denied',
    ]))->toBe(0)
        ->and(OrganisationAccessHold::query()->where('incident_uuid', $incident->id)->count())->toBe(2);
});

it('releases only the selected incident controls and records a recovery gate', function () {
    $actor = User::factory()->create();
    $incident = SecurityIncident::factory()->create(['status' => SecurityIncidentStatus::Contained]);
    $otherIncident = SecurityIncident::factory()->create(['status' => SecurityIncidentStatus::Contained]);
    InstallationControl::factory()->for($incident, 'incident')->create(['capability' => InstallationCapability::Writes]);
    InstallationControl::factory()->for($otherIncident, 'incident')->create(['capability' => InstallationCapability::Writes]);

    expect(Artisan::call('security:incident:recover', [
        'incident' => $incident->id,
        '--confirm' => $incident->id,
        '--actor-user' => $actor->id,
        '--reason-code' => IncidentReasonCode::SyntheticExercise->value,
    ]))->toBe(0)
        ->and($incident->fresh()->status)->toBe(SecurityIncidentStatus::Recovering)
        ->and($incident->installationControls()->whereNull('released_at')->count())->toBe(0)
        ->and($otherIncident->installationControls()->whereNull('released_at')->count())->toBe(1)
        ->and($incident->entries()->where('type', SecurityIncidentEntryType::RecoveryGate)->count())->toBe(1);
});

it('prevents platform event and incident entry tampering at the database boundary', function () {
    $incident = SecurityIncident::factory()->create();
    $entry = SecurityIncidentEntry::factory()->for($incident, 'incident')->create();
    $event = PlatformSecurityEvent::factory()->create();

    expect(fn () => DB::transaction(
        fn () => DB::table('security_incident_entries')->where('id', $entry->id)->update(['summary' => 'tampered']),
    ))->toThrow(QueryException::class, 'append-only');
    expect(fn () => DB::transaction(
        fn () => DB::table('platform_security_events')->where('id', $event->id)->delete(),
    ))->toThrow(QueryException::class, 'append-only');
});

it('preserves immutable actor references without blocking account deletion', function () {
    $actor = User::factory()->create();
    $event = PlatformSecurityEvent::factory()->create([
        'actor_user_id' => $actor->id,
        'subject_user_id' => $actor->id,
    ]);

    $actor->delete();

    $this->assertDatabaseMissing('users', ['id' => $actor->id]);
    $this->assertDatabaseHas('platform_security_events', [
        'id' => $event->id,
        'actor_user_id' => $actor->id,
        'subject_user_id' => $actor->id,
    ]);
});
