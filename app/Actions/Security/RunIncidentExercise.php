<?php

namespace App\Actions\Security;

use App\Actions\Auditing\CreateDailyAuditDigest;
use App\Enums\PlatformSecurityEventType;
use App\Enums\SecurityIncidentClassification;
use App\Enums\SecurityIncidentEntryType;
use App\Enums\SecurityIncidentSeverity;
use App\Enums\SecurityIncidentStatus;
use App\Models\SecurityIncident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

class RunIncidentExercise
{
    public function __construct(
        private readonly ReportSecurityIncident $reportIncident,
        private readonly RecordSecurityIncidentEntry $recordEntry,
        private readonly RecordPlatformSecurityEvent $recordSecurityEvent,
        private readonly CreateDailyAuditDigest $canonicalizer,
    ) {}

    /** @return array{exerciseId: string, path: string, digest: string, signature: string, incidentIds: list<string>} */
    public function handle(?User $actor = null): array
    {
        $key = config('audit_integrity.signing_key');
        if (! is_string($key) || $key === '') {
            throw new LogicException('AUDIT_DIGEST_SIGNING_KEY must be configured.');
        }

        $exerciseId = (string) Str::ulid();
        $generatedAt = now()->utc()->toAtomString();
        $packScenarios = [];
        $incidentIds = [];

        foreach ($this->scenarios() as $scenario) {
            $incident = DB::transaction(function () use ($actor, $exerciseId, $generatedAt, &$packScenarios, $scenario) {
                $incident = $this->reportIncident->handle(
                    SecurityIncidentClassification::Incident,
                    $scenario['severity'],
                    'synthetic_exercise',
                    'Synthetic exercise: '.str_replace('_', ' ', $scenario['slug']).'.',
                    $actor,
                );
                $evidenceReference = 'exercise:'.$exerciseId.':'.$scenario['slug'].':evidence';
                $originalDigest = hash('sha256', $exerciseId.'|'.$scenario['slug'].'|original');
                $workingDigest = hash('sha256', $exerciseId.'|'.$scenario['slug'].'|working-copy');
                $correctiveDueAt = now()->addDays(30);

                $this->recordEntry->handle($incident, SecurityIncidentEntryType::Decision, 'Containment decision: '.$scenario['containment'], $actor);
                $this->recordEntry->handle($incident, SecurityIncidentEntryType::Decision, 'Communications decision: '.$scenario['communications'], $actor);
                $this->recordEntry->handle($incident, SecurityIncidentEntryType::EvidenceReference, 'Synthetic content-free evidence captured and hashed.', $actor, reference: $evidenceReference);
                $this->recordEntry->handle($incident, SecurityIncidentEntryType::Action, 'Exercise scenario executed without tenant-content or break-glass access.', $actor, status: 'completed');
                $this->recordEntry->handle($incident, SecurityIncidentEntryType::RecoveryGate, $scenario['recovery_gate'], $actor, status: 'passed');
                $this->recordEntry->handle($incident, SecurityIncidentEntryType::CorrectiveAction, $scenario['corrective_action'], $actor, status: 'open', dueAt: $correctiveDueAt);
                foreach ([
                    SecurityIncidentStatus::Triaging,
                    SecurityIncidentStatus::Confirmed,
                    SecurityIncidentStatus::Contained,
                    SecurityIncidentStatus::Eradicated,
                    SecurityIncidentStatus::Recovering,
                    SecurityIncidentStatus::Monitoring,
                    SecurityIncidentStatus::Closed,
                ] as $status) {
                    $this->transition($incident, $status, $actor);
                }

                $packScenarios[] = [
                    'scenario' => $scenario['slug'],
                    'incident_uuid' => $incident->id,
                    'severity' => $scenario['severity']->value,
                    'containment_decision' => $scenario['containment'],
                    'communications_decision' => $scenario['communications'],
                    'evidence_chain' => [
                        'reference' => $evidenceReference,
                        'collected_at' => $generatedAt,
                        'source' => 'synthetic_exercise',
                        'collector' => 'synthetic_exercise_runner',
                        'method' => 'community_kind_incident_exercise',
                        'tool_version' => (string) config('source.release'),
                        'classification' => 'synthetic_content_free',
                        'storage_location' => 'signed_redacted_evidence_pack',
                        'retention_state' => 'synthetic_exercise_default',
                        'legal_hold' => false,
                        'original_digest' => $originalDigest,
                        'working_copy_digest' => $workingDigest,
                        'custody_log' => [[
                            'action' => 'created',
                            'occurred_at' => $generatedAt,
                            'actor' => 'synthetic_exercise_runner',
                        ]],
                    ],
                    'recovery_gate' => [
                        'summary' => $scenario['recovery_gate'],
                        'status' => 'passed',
                    ],
                    'corrective_action' => [
                        'summary' => $scenario['corrective_action'],
                        'status' => 'open',
                        'due_at' => $correctiveDueAt->utc()->toAtomString(),
                    ],
                ];

                return $incident;
            });
            $incidentIds[] = $incident->id;
        }

        $payload = [
            'schema_version' => 1,
            'exercise_id' => $exerciseId,
            'generated_at' => $generatedAt,
            'fictional_data' => true,
            'access_mode' => 'synthetic_metadata_only_no_break_glass',
            'scenarios' => $packScenarios,
        ];
        $digest = hash('sha256', $this->canonicalizer->canonicalJson($payload));
        $signature = hash_hmac('sha256', $digest, $key);
        $pack = $payload + ['pack_digest' => $digest, 'signature' => $signature];
        $path = 'incident-exercises/'.$exerciseId.'/evidence-pack.json';
        Storage::disk((string) config('audit_integrity.disk'))->put($path, $this->canonicalizer->canonicalJson($pack)."\n");
        $this->recordSecurityEvent->handle(PlatformSecurityEventType::IncidentExerciseCompleted, [
            'exercise_id' => $exerciseId,
            'scenario_count' => count($packScenarios),
            'pack_digest' => $digest,
        ], $actor);

        return [
            'exerciseId' => $exerciseId,
            'path' => $path,
            'digest' => $digest,
            'signature' => $signature,
            'incidentIds' => $incidentIds,
        ];
    }

    private function transition(SecurityIncident $incident, SecurityIncidentStatus $to, ?User $actor): void
    {
        $from = $incident->status;
        $attributes = ['status' => $to];
        if ($to === SecurityIncidentStatus::Confirmed) {
            $attributes['first_awareness_at'] = now();
            $attributes['confirmed_at'] = now();
            $attributes['commander_user_id'] = $actor?->id;
        }
        if ($to === SecurityIncidentStatus::Closed) {
            $attributes['closed_at'] = now();
        }

        $incident->update($attributes);
        $this->recordEntry->handle(
            $incident,
            SecurityIncidentEntryType::StatusChange,
            'Synthetic exercise status changed from '.$from->value.' to '.$to->value.'.',
            $actor,
            status: $to->value,
        );
        $this->recordSecurityEvent->handle(PlatformSecurityEventType::IncidentStatusChanged, [
            'incident_uuid' => $incident->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
        ], $actor, incidentUuid: $incident->id);
    }

    /** @return list<array{slug: string, severity: SecurityIncidentSeverity, containment: string, communications: string, recovery_gate: string, corrective_action: string}> */
    private function scenarios(): array
    {
        return [
            [
                'slug' => 'cross_tenant_exposure',
                'severity' => SecurityIncidentSeverity::S1Critical,
                'containment' => 'Freeze affected tenant writes, exports, and sessions while preserving tenant separation.',
                'communications' => 'Prepare separate approved tenant projections; do not disclose another Organisation.',
                'recovery_gate' => 'Tenant-boundary tests and audit-chain reconciliation passed.',
                'corrective_action' => 'Add a regression test for the failed tenant-boundary control.',
            ],
            [
                'slug' => 'privileged_account_compromise',
                'severity' => SecurityIncidentSeverity::S1Critical,
                'containment' => 'Revoke installation-wide sessions and coordinate scoped credential rotation.',
                'communications' => 'Use the independent security contact path until application integrity is trusted.',
                'recovery_gate' => 'Privileged sessions, tokens, and provider credentials reconciled.',
                'corrective_action' => 'Review privileged-role scope and session telemetry.',
            ],
            [
                'slug' => 'malicious_file_release',
                'severity' => SecurityIncidentSeverity::S2High,
                'containment' => 'Disable downloads and uploads, isolate released objects, and preserve content-free evidence references.',
                'communications' => 'Notify only confirmed affected Organisations after human approval.',
                'recovery_gate' => 'Object inventory, malware rescan, and release-state reconciliation passed.',
                'corrective_action' => 'Strengthen release-generation and scanner-result verification.',
            ],
            [
                'slug' => 'key_disclosure',
                'severity' => SecurityIncidentSeverity::S1Critical,
                'containment' => 'Pause affected writes and coordinate data, index, and provider-key rotation.',
                'communications' => 'Record controller and processor notification decisions without assuming a universal deadline.',
                'recovery_gate' => 'Live data and retained recovery sets prove supported key versions.',
                'corrective_action' => 'Reduce key exposure paths and rehearse independent recovery custody.',
            ],
            [
                'slug' => 'audit_backup_integrity_failure',
                'severity' => SecurityIncidentSeverity::S1Critical,
                'containment' => 'Freeze destructive recovery actions and preserve database and offsite copies independently.',
                'communications' => 'Use an independent status channel while audit-system integrity is uncertain.',
                'recovery_gate' => 'Audit chain, deletion ledger, and representative restore all reconcile.',
                'corrective_action' => 'Repair monitoring and add a digest or restore regression fixture.',
            ],
        ];
    }
}
