<?php

namespace App\Actions\Configuration;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\TenantAuditEventType;
use App\Models\OrganisationConfiguration;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ActivateOrganisationConfiguration
{
    public function __construct(private readonly OrganisationContext $context, private readonly ValidateConfigurationDefinition $validate, private readonly RecordTenantAuditEvent $recordAudit) {}

    public function handle(OrganisationConfiguration $configuration, User $actor): OrganisationConfiguration
    {
        $this->context->ensureOwns($configuration->organisation_id);

        return DB::transaction(function () use ($actor, $configuration): OrganisationConfiguration {
            $latest = OrganisationConfiguration::query()->where('area', $configuration->area)->where('configuration_key', $configuration->configuration_key)->lockForUpdate()->latest('version')->firstOrFail();
            $locked = OrganisationConfiguration::query()->findOrFail($configuration->id);
            if ($locked->status !== OrganisationConfigurationStatus::Draft) {
                throw new LogicException('Only a draft configuration version can be activated.');
            }
            if ($locked->id !== $latest->id) {
                throw new LogicException('Only the latest configuration version can be activated. Create a new version to roll back.');
            }
            $this->validate->handle($locked->area, $locked->definition);
            /*
             * Superseded one row at a time rather than as a query-builder
             * update. A mass update fires no model events, so the status
             * transition guard on the model never ran for this path: it looked
             * protected but was not. Only versions that are actually active are
             * touched; every other status is left as it is.
             */
            $superseding = OrganisationConfiguration::query()
                ->where('area', $locked->area)
                ->where('configuration_key', $locked->configuration_key)
                ->where('status', OrganisationConfigurationStatus::Active)
                ->lockForUpdate()
                ->get();

            foreach ($superseding as $active) {
                $active->update(['status' => OrganisationConfigurationStatus::Superseded]);
            }
            $locked->update(['status' => OrganisationConfigurationStatus::Active, 'activated_by_user_id' => $actor->id, 'activated_at' => now()]);
            $this->recordAudit->handle($locked->organisation, TenantAuditEventType::OrganisationConfigurationActivated, 'organisation_configuration', $locked->id, ['configuration_id' => $locked->id, 'area' => $locked->area->value, 'configuration_key' => $locked->configuration_key, 'version' => $locked->version], $actor);

            return $locked->refresh();
        });
    }
}
