<?php

namespace App\Actions\Security;

use App\Enums\PlatformSecurityEventType;
use App\Enums\SecurityIncidentClassification;
use App\Enums\SecurityIncidentSeverity;
use App\Enums\SecurityIncidentStatus;
use App\Models\SecurityIncident;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportSecurityIncident
{
    public function __construct(private RecordPlatformSecurityEvent $recordPlatformSecurityEvent) {}

    public function handle(
        SecurityIncidentClassification $classification,
        SecurityIncidentSeverity $severity,
        string $detectionSource,
        string $summary,
        ?User $actor = null,
    ): SecurityIncident {
        return DB::transaction(function () use ($classification, $severity, $detectionSource, $summary, $actor): SecurityIncident {
            $incident = SecurityIncident::query()->create([
                'classification' => $classification,
                'severity' => $severity,
                'status' => SecurityIncidentStatus::Reported,
                'detection_source' => $detectionSource,
                'summary' => $summary,
                'detected_at' => now(),
            ]);

            $this->recordPlatformSecurityEvent->handle(
                PlatformSecurityEventType::IncidentReported,
                [
                    'incident_uuid' => $incident->id,
                    'classification' => $classification->value,
                    'severity' => $severity->value,
                    'detection_source' => $detectionSource,
                ],
                actor: $actor,
                incidentUuid: $incident->id,
            );

            return $incident;
        });
    }
}
