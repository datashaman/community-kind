<?php

namespace App\Actions\Security;

use App\Actions\Organisations\ReleaseOrganisationAccessHold;
use App\Enums\IncidentReasonCode;
use App\Enums\PlatformSecurityEventType;
use App\Enums\SecurityIncidentEntryType;
use App\Enums\SecurityIncidentStatus;
use App\Models\InstallationControl;
use App\Models\OrganisationAccessHold;
use App\Models\SecurityIncident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class RecoverIncidentContainment
{
    public function __construct(
        private ReleaseOrganisationAccessHold $releaseOrganisationAccessHold,
        private RecordPlatformSecurityEvent $recordPlatformSecurityEvent,
        private RecordSecurityIncidentEntry $recordSecurityIncidentEntry,
    ) {}

    public function handle(SecurityIncident $incident, User $actor, IncidentReasonCode $reasonCode): SecurityIncident
    {
        return DB::transaction(function () use ($incident, $actor, $reasonCode): SecurityIncident {
            $incident = SecurityIncident::query()->lockForUpdate()->findOrFail($incident->id);

            if ($incident->status !== SecurityIncidentStatus::Contained) {
                throw new LogicException('Containment can only be recovered from a contained incident.');
            }

            OrganisationAccessHold::query()
                ->with('organisation')
                ->where('incident_uuid', $incident->id)
                ->whereNull('released_at')
                ->each(function (OrganisationAccessHold $hold) use ($incident, $actor, $reasonCode): void {
                    $this->releaseOrganisationAccessHold->handle($hold, $actor->email, $reasonCode->value, $actor);
                    $this->recordPlatformSecurityEvent->handle(
                        PlatformSecurityEventType::OrganisationAccessHoldReleased,
                        [
                            'incident_uuid' => $incident->id,
                            'organisation_uuid' => $hold->organisation->uuid,
                            'hold_id' => $hold->id,
                            'reason_code' => $reasonCode->value,
                        ],
                        actor: $actor,
                        incidentUuid: $incident->id,
                    );
                });

            InstallationControl::query()
                ->where('incident_uuid', $incident->id)
                ->whereNull('released_at')
                ->each(function (InstallationControl $control) use ($incident, $actor, $reasonCode): void {
                    $control->update([
                        'released_by_user_id' => $actor->id,
                        'released_at' => now(),
                        'release_reason_code' => $reasonCode->value,
                    ]);
                    $this->recordPlatformSecurityEvent->handle(
                        PlatformSecurityEventType::InstallationControlReleased,
                        [
                            'incident_uuid' => $incident->id,
                            'control_id' => $control->id,
                            'capability' => $control->capability->value,
                            'reason_code' => $reasonCode->value,
                        ],
                        actor: $actor,
                        incidentUuid: $incident->id,
                    );
                });

            $incident->update(['status' => SecurityIncidentStatus::Recovering]);
            $this->recordSecurityIncidentEntry->handle(
                $incident,
                SecurityIncidentEntryType::RecoveryGate,
                'Containment rollback completed; verification is required before closure.',
                $actor,
                status: 'passed',
            );
            $this->recordPlatformSecurityEvent->handle(
                PlatformSecurityEventType::IncidentStatusChanged,
                [
                    'incident_uuid' => $incident->id,
                    'from_status' => SecurityIncidentStatus::Contained->value,
                    'to_status' => SecurityIncidentStatus::Recovering->value,
                ],
                actor: $actor,
                incidentUuid: $incident->id,
            );

            return $incident;
        });
    }
}
