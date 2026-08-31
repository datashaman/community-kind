<?php

namespace App\Actions\Security;

use App\Actions\Organisations\PlaceOrganisationAccessHold;
use App\Enums\IncidentReasonCode;
use App\Enums\InstallationCapability;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Enums\PlatformSecurityEventType;
use App\Enums\SecurityIncidentClassification;
use App\Enums\SecurityIncidentEntryType;
use App\Enums\SecurityIncidentStatus;
use App\Models\InstallationControl;
use App\Models\Organisation;
use App\Models\OrganisationAccessHold;
use App\Models\SecurityIncident;
use App\Models\SecurityIncidentOrganisation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class ApplyIncidentContainment
{
    public function __construct(
        private RevokeUserAccess $revokeUserAccess,
        private PlaceOrganisationAccessHold $placeOrganisationAccessHold,
        private RecordPlatformSecurityEvent $recordPlatformSecurityEvent,
        private RecordSecurityIncidentEntry $recordSecurityIncidentEntry,
    ) {}

    /**
     * @param  list<User>  $users
     * @param  list<Organisation>  $organisations
     * @param  list<InstallationCapability>  $capabilities
     * @param  list<string>  $credentialReferences
     */
    public function handle(
        SecurityIncident $incident,
        User $actor,
        IncidentReasonCode $reasonCode,
        array $users = [],
        array $organisations = [],
        array $capabilities = [],
        array $credentialReferences = [],
        OrganisationAccessScope $holdScope = OrganisationAccessScope::All,
        OrganisationAccessLevel $holdLevel = OrganisationAccessLevel::Denied,
        ?Carbon $reviewAt = null,
        ?Carbon $expiresAt = null,
    ): SecurityIncident {
        return DB::transaction(function () use ($incident, $actor, $reasonCode, $users, $organisations, $capabilities, $credentialReferences, $holdScope, $holdLevel, $reviewAt, $expiresAt): SecurityIncident {
            $incident = SecurityIncident::query()->lockForUpdate()->findOrFail($incident->id);

            if ($incident->classification !== SecurityIncidentClassification::Incident || ! $incident->status->allowsContainment()) {
                throw new LogicException('Only an open incident can authorize containment.');
            }

            foreach ($users as $user) {
                $counts = $this->revokeUserAccess->handle($user, $actor);
                $this->recordPlatformSecurityEvent->handle(
                    PlatformSecurityEventType::UserAccessRevoked,
                    ['incident_uuid' => $incident->id, 'user_id' => $user->id, ...$counts],
                    actor: $actor,
                    subject: $user,
                    incidentUuid: $incident->id,
                );
            }

            foreach ($organisations as $organisation) {
                $existingHold = OrganisationAccessHold::query()
                    ->where('incident_uuid', $incident->id)
                    ->where('organisation_id', $organisation->id)
                    ->where('scope', $holdScope)
                    ->whereNull('released_at')
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->first();

                if ($existingHold !== null && $existingHold->access_level->rank() >= $holdLevel->rank()) {
                    continue;
                }

                $hold = $this->placeOrganisationAccessHold->handle(
                    $organisation,
                    $actor->email,
                    $reasonCode->value,
                    $holdScope,
                    $holdLevel,
                    $reviewAt ?? Carbon::now()->addDay(),
                    $expiresAt ?? Carbon::now()->addDays(3),
                    $actor,
                    $incident->id,
                );
                SecurityIncidentOrganisation::query()->updateOrCreate(
                    ['incident_uuid' => $incident->id, 'organisation_id' => $organisation->id],
                    ['impact_status' => 'investigating'],
                );
                $this->recordPlatformSecurityEvent->handle(
                    PlatformSecurityEventType::OrganisationAccessHoldApplied,
                    [
                        'incident_uuid' => $incident->id,
                        'organisation_uuid' => $organisation->uuid,
                        'hold_id' => $hold->id,
                        'scope' => $holdScope->value,
                        'access_level' => $holdLevel->value,
                        'reason_code' => $reasonCode->value,
                    ],
                    actor: $actor,
                    incidentUuid: $incident->id,
                );
            }

            foreach ($capabilities as $capability) {
                $control = InstallationControl::query()->firstOrCreate(
                    [
                        'incident_uuid' => $incident->id,
                        'capability' => $capability,
                        'released_at' => null,
                    ],
                    [
                        'reason_code' => $reasonCode->value,
                        'activated_by_user_id' => $actor->id,
                        'activated_at' => now(),
                    ],
                );

                if (! $control->wasRecentlyCreated) {
                    continue;
                }

                $this->recordPlatformSecurityEvent->handle(
                    PlatformSecurityEventType::InstallationControlApplied,
                    [
                        'incident_uuid' => $incident->id,
                        'control_id' => $control->id,
                        'capability' => $capability->value,
                        'reason_code' => $reasonCode->value,
                    ],
                    actor: $actor,
                    incidentUuid: $incident->id,
                );
            }

            foreach ($credentialReferences as $credentialReference) {
                $this->recordPlatformSecurityEvent->handle(
                    PlatformSecurityEventType::CredentialRotationCoordinated,
                    [
                        'incident_uuid' => $incident->id,
                        'credential_reference' => $credentialReference,
                    ],
                    actor: $actor,
                    incidentUuid: $incident->id,
                );
            }

            $fromStatus = $incident->status;
            $incident->update([
                'status' => SecurityIncidentStatus::Contained,
                'confirmed_at' => $incident->confirmed_at ?? now(),
                'first_awareness_at' => $incident->first_awareness_at ?? now(),
                'commander_user_id' => $incident->commander_user_id ?? $actor->id,
            ]);
            $this->recordSecurityIncidentEntry->handle(
                $incident,
                SecurityIncidentEntryType::Action,
                'Containment controls applied.',
                $actor,
                status: 'completed',
            );
            if ($fromStatus !== SecurityIncidentStatus::Contained) {
                $this->recordPlatformSecurityEvent->handle(
                    PlatformSecurityEventType::IncidentStatusChanged,
                    [
                        'incident_uuid' => $incident->id,
                        'from_status' => $fromStatus->value,
                        'to_status' => SecurityIncidentStatus::Contained->value,
                    ],
                    actor: $actor,
                    incidentUuid: $incident->id,
                );
            }

            return $incident;
        });
    }
}
