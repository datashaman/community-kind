<?php

namespace App\Actions\Reporting;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\TenantAuditEventType;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\PublishedImpactSnapshot;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class PublishImpactSnapshot
{
    public function __construct(private readonly OrganisationContext $context, private readonly BuildImpactDashboard $dashboard, private readonly RecordTenantAuditEvent $recordAudit) {}

    /** @param array<string, mixed> $filters */
    public function handle(Organisation $organisation, User $actor, string $audience, array $filters): PublishedImpactSnapshot
    {
        $this->context->ensureOwns($organisation->id);
        if (! in_array($audience, ['board', 'funder', 'public'], true)) {
            throw new LogicException('Impact snapshot audience is invalid.');
        }
        $configuration = OrganisationConfiguration::query()->where('area', OrganisationConfigurationArea::Reporting)->where('configuration_key', 'impact')->where('status', OrganisationConfigurationStatus::Active)->latest('version')->first();
        if ($configuration === null) {
            throw new LogicException('An active impact reporting configuration is required.');
        }
        $configuredIds = $configuration->definition[$audience === 'public' ? 'public_metric_ids' : 'pack_metric_ids'] ?? null;
        $allowedIds = collect(is_array($configuredIds) ? array_values(array_filter($configuredIds, is_string(...))) : []);
        $reconciled = $this->dashboard->handle($actor, $organisation, $filters);
        $reconciledMetrics = $reconciled['metrics'] ?? null;
        $reconciledCohorts = $reconciled['cohortComparisons'] ?? null;
        if (! is_array($reconciledMetrics) || ! is_array($reconciledCohorts)) {
            throw new LogicException('Reconciled impact data is malformed.');
        }
        $metrics = collect($reconciledMetrics)->filter(fn (mixed $metric): bool => is_array($metric) && is_array($metric['definition'] ?? null) && $allowedIds->contains($metric['definition']['id'] ?? null))->values();
        if ($metrics->isEmpty()) {
            throw new LogicException('The active reporting configuration does not approve any available metrics for this audience.');
        }
        $cohorts = collect($reconciledCohorts)->filter(fn (mixed $cohort): bool => is_array($cohort))->map(function (array $cohort) use ($allowedIds): array {
            $cohortMetrics = is_array($cohort['metrics'] ?? null) ? $cohort['metrics'] : [];

            return [...$cohort, 'metrics' => collect($cohortMetrics)->only($allowedIds->all())->all()];
        })->values()->all();

        return DB::transaction(function () use ($actor, $audience, $cohorts, $filters, $metrics, $organisation, $reconciled): PublishedImpactSnapshot {
            $snapshot = PublishedImpactSnapshot::query()->create([
                'organisation_id' => $organisation->id,
                'audience' => $audience,
                'registry_version' => $reconciled['registryVersion'],
                'metrics' => $metrics->all(),
                'cohort_comparisons' => $cohorts,
                'period' => $reconciled['period'],
                'filters' => $filters,
                'approved_at' => now(),
                'approved_by_user_id' => $actor->id,
                'published_at' => $audience === 'public' ? now() : null,
            ]);
            $this->recordAudit->handle($organisation, TenantAuditEventType::ImpactSnapshotPublished, 'published_impact_snapshot', $snapshot->id, ['snapshot_id' => $snapshot->id, 'audience' => $audience, 'registry_version' => $snapshot->registry_version, 'metric_count' => $metrics->count()], $actor);

            return $snapshot;
        });
    }
}
