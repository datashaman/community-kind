<?php

namespace App\Actions\Reporting;

use App\Models\PublishedImpactSnapshot;
use App\OrganisationContext;
use LogicException;

final class BuildImpactReportPack
{
    public function __construct(private readonly OrganisationContext $context) {}

    /** @return list<list<string|int|float|null>> */
    public function handle(PublishedImpactSnapshot $snapshot): array
    {
        $this->context->ensureOwns($snapshot->organisation_id);
        if (! in_array($snapshot->audience, ['board', 'funder'], true)) {
            throw new LogicException('Only board and funder snapshots can be downloaded as packs.');
        }
        $rows = [['section', 'cohort', 'metric', 'value', 'availability', 'unit', 'registry_version', 'period_start', 'period_end_exclusive']];
        foreach ($snapshot->metrics as $metric) {
            $rows[] = ['headline', '', $metric['definition']['label'], $metric['value'], $metric['availability'], $metric['definition']['unit'], $snapshot->registry_version, $snapshot->period['start'], $snapshot->period['endExclusive']];
        }
        foreach ($snapshot->cohort_comparisons as $cohort) {
            foreach ($cohort['metrics'] as $metricId => $metric) {
                $rows[] = ['cohort', $cohort['label'], $metricId, $metric['value'], $metric['availability'], '', $snapshot->registry_version, $snapshot->period['start'], $snapshot->period['endExclusive']];
            }
        }

        return $rows;
    }
}
