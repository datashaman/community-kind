<?php

namespace App\Console\Commands;

use App\Actions\Security\ApplyIncidentContainment;
use App\Enums\IncidentReasonCode;
use App\Enums\InstallationCapability;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Models\Organisation;
use App\Models\SecurityIncident;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('security:incident:contain
    {incident : Incident UUID}
    {--confirm= : Repeat the incident UUID to authorize the operation}
    {--actor-user= : Installation Operator User ID}
    {--reason-code= : Allowlisted containment reason code}
    {--revoke-user=* : User IDs whose installation-wide access must be revoked}
    {--hold-organisation=* : Organisation UUIDs to hold}
    {--hold-scope=all : Organisation Access Hold scope}
    {--hold-level=denied : Organisation Access Hold level}
    {--pause=* : Installation capabilities to pause: queues, outbox, uploads, or forms}
    {--freeze-writes : Apply an Installation-wide write freeze}
    {--credential=* : Opaque credential references requiring coordinated rotation}')]
#[Description('Apply reason-coded, audited containment for a registered security incident')]
class ContainSecurityIncident extends Command
{
    public function handle(ApplyIncidentContainment $applyIncidentContainment): int
    {
        $incidentId = (string) $this->argument('incident');

        if ($this->option('confirm') !== $incidentId) {
            $this->error('Containment requires --confirm to exactly repeat the incident UUID.');

            return self::FAILURE;
        }

        $reasonCode = IncidentReasonCode::tryFrom((string) $this->option('reason-code'));
        $holdScope = OrganisationAccessScope::tryFrom((string) $this->option('hold-scope'));
        $holdLevel = OrganisationAccessLevel::tryFrom((string) $this->option('hold-level'));

        if ($reasonCode === null || $holdScope === null || $holdLevel === null) {
            $this->error('The reason code, hold scope, or hold level is not allowlisted.');

            return self::INVALID;
        }

        $capabilityValues = $this->stringListOption('pause');
        if ($this->option('freeze-writes')) {
            $capabilityValues[] = InstallationCapability::Writes->value;
        }
        $capabilities = [];
        foreach (array_unique($capabilityValues) as $value) {
            $capability = InstallationCapability::tryFrom($value);
            if ($capability === null) {
                $this->error('One or more paused capabilities are not allowlisted.');

                return self::INVALID;
            }

            $capabilities[] = $capability;
        }

        $credentialReferences = $this->stringListOption('credential');
        foreach ($credentialReferences as $reference) {
            if (preg_match('/^[a-zA-Z0-9._:-]+$/', $reference) === 1) {
                continue;
            }

            $this->error('Credential options must be opaque references, not secrets or free text.');

            return self::INVALID;
        }

        $revokedUserIds = $this->stringListOption('revoke-user');
        $heldOrganisationUuids = $this->stringListOption('hold-organisation');

        if ($capabilities === [] && $credentialReferences === [] && $revokedUserIds === [] && $heldOrganisationUuids === []) {
            $this->error('Select at least one containment action.');

            return self::INVALID;
        }

        $revokedUserIds = array_values(array_unique($revokedUserIds));
        $heldOrganisationUuids = array_values(array_unique($heldOrganisationUuids));
        $users = array_values(User::query()->whereKey($revokedUserIds)->get()->all());
        $organisations = array_values(Organisation::query()->whereIn('uuid', $heldOrganisationUuids)->get()->all());

        if (count($users) !== count($revokedUserIds) || count($organisations) !== count($heldOrganisationUuids)) {
            $this->error('Every requested User and Organisation target must exist before containment begins.');

            return self::INVALID;
        }

        try {
            $applyIncidentContainment->handle(
                SecurityIncident::query()->findOrFail($incidentId),
                User::query()->findOrFail((int) $this->option('actor-user')),
                $reasonCode,
                $users,
                $organisations,
                $capabilities,
                $credentialReferences,
                $holdScope,
                $holdLevel,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Containment applied for incident {$incidentId}.");

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function stringListOption(string $name): array
    {
        $values = $this->option($name);

        if (! is_array($values)) {
            return [];
        }

        $strings = [];
        foreach ($values as $value) {
            if (is_string($value) || is_int($value)) {
                $strings[] = (string) $value;
            }
        }

        return $strings;
    }
}
