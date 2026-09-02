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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Takes a whole configuration key out of service.
 *
 * Configuration history is immutable and the model forbids deletion, so a key
 * that should no longer be used is retired rather than removed. Every version
 * still in play (draft or active) moves to retired, leaving no version of the
 * key active. Superseded versions are left alone: they are already history.
 *
 * Retirement is terminal per version. A retired key is reinstated by creating
 * a new version of it, which makes the key's latest version a draft again.
 */
final class RetireOrganisationConfiguration
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    /** @return Collection<int, OrganisationConfiguration> */
    public function handle(Organisation $organisation, OrganisationConfigurationArea $area, string $key, User $actor): Collection
    {
        $this->context->ensureOwns($organisation->id);

        return DB::transaction(function () use ($actor, $area, $key, $organisation): Collection {
            /** @var Collection<int, OrganisationConfiguration> $versions */
            $versions = OrganisationConfiguration::query()
                ->where('area', $area)
                ->where('configuration_key', $key)
                ->lockForUpdate()
                ->orderBy('version')
                ->get();

            if ($versions->isEmpty()) {
                throw new LogicException("No {$area->value} configuration exists for key {$key}.");
            }

            $retirable = $versions->whereIn('status', [
                OrganisationConfigurationStatus::Draft,
                OrganisationConfigurationStatus::Active,
            ]);

            if ($retirable->isEmpty()) {
                throw new LogicException("Configuration key {$key} is already retired.");
            }

            /*
             * Saved one at a time rather than as a mass update so the model's
             * status transition guard actually runs; a query-builder update
             * would bypass it.
             */
            foreach ($retirable as $version) {
                $version->update(['status' => OrganisationConfigurationStatus::Retired]);

                $this->recordAudit->handle(
                    $organisation,
                    TenantAuditEventType::OrganisationConfigurationRetired,
                    'organisation_configuration',
                    $version->id,
                    [
                        'configuration_id' => $version->id,
                        'area' => $area->value,
                        'configuration_key' => $key,
                        'version' => $version->version,
                    ],
                    $actor,
                );
            }

            return $retirable->values();
        });
    }
}
