<?php

namespace App\Actions\Configuration;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\TenantAuditEventType;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

final class CreateOrganisationConfiguration
{
    public function __construct(private readonly OrganisationContext $context, private readonly ValidateConfigurationDefinition $validate, private readonly RecordTenantAuditEvent $recordAudit) {}

    /** @param array<string, mixed> $definition */
    public function handle(Organisation $organisation, OrganisationConfigurationArea $area, string $key, array $definition, User $actor): OrganisationConfiguration
    {
        $this->context->ensureOwns($organisation->id);
        $definition = $this->validate->handle($area, $definition);

        return DB::transaction(function () use ($actor, $area, $definition, $key, $organisation): OrganisationConfiguration {
            $latest = OrganisationConfiguration::query()->where('area', $area)->where('configuration_key', $key)->lockForUpdate()->latest('version')->first();
            $nextVersion = $latest instanceof OrganisationConfiguration ? $latest->version + 1 : 1;
            $supersedesId = $latest instanceof OrganisationConfiguration ? $latest->id : null;
            $configuration = OrganisationConfiguration::query()->create([
                'organisation_id' => $organisation->id,
                'area' => $area,
                'configuration_key' => $key,
                'version' => $nextVersion,
                'definition' => $definition,
                'status' => OrganisationConfigurationStatus::Draft,
                'supersedes_id' => $supersedesId,
                'created_by_user_id' => $actor->id,
            ]);
            $this->recordAudit->handle($organisation, TenantAuditEventType::OrganisationConfigurationCreated, 'organisation_configuration', $configuration->id, ['configuration_id' => $configuration->id, 'area' => $area->value, 'configuration_key' => $key, 'version' => $configuration->version], $actor);

            return $configuration;
        });
    }
}
