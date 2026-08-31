<?php

namespace App\Console\Commands;

use App\Actions\Security\RecoverIncidentContainment;
use App\Enums\IncidentReasonCode;
use App\Models\SecurityIncident;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('security:incident:recover
    {incident : Incident UUID}
    {--confirm= : Repeat the incident UUID to authorize the operation}
    {--actor-user= : Installation Operator User ID}
    {--reason-code= : Allowlisted recovery reason code}')]
#[Description('Release incident-scoped containment and enter verified recovery')]
class RecoverSecurityIncident extends Command
{
    public function handle(RecoverIncidentContainment $recoverIncidentContainment): int
    {
        $incidentId = (string) $this->argument('incident');

        if ($this->option('confirm') !== $incidentId) {
            $this->error('Recovery requires --confirm to exactly repeat the incident UUID.');

            return self::FAILURE;
        }

        $reasonCode = IncidentReasonCode::tryFrom((string) $this->option('reason-code'));
        if ($reasonCode === null) {
            $this->error('The recovery reason code is not allowlisted.');

            return self::INVALID;
        }

        try {
            $recoverIncidentContainment->handle(
                SecurityIncident::query()->findOrFail($incidentId),
                User::query()->findOrFail((int) $this->option('actor-user')),
                $reasonCode,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Containment released for incident {$incidentId}; recovery verification is required.");

        return self::SUCCESS;
    }
}
