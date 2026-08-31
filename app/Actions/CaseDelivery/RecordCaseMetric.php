<?php

namespace App\Actions\CaseDelivery;

use App\Enums\CaseMetricCode;
use App\Models\MetricEvent;
use App\Models\ServiceCase;
use Carbon\CarbonInterface;

class RecordCaseMetric
{
    /** @param array<string, bool|int|float|string|null> $dimensions */
    public function handle(ServiceCase $case, CaseMetricCode $code, CarbonInterface $occurredAt, string $sourceKey, array $dimensions = [], float $value = 1): MetricEvent
    {
        return MetricEvent::query()->firstOrCreate(
            ['deduplication_key' => hash('sha256', implode('|', [$case->organisation_id, $code->value, $sourceKey]))],
            [
                'organisation_id' => $case->organisation_id,
                'program_id' => $case->program_id,
                'code' => $code,
                'value' => $value,
                'dimensions' => [...$dimensions, 'party_id' => $case->party_id],
                'occurred_at' => $occurredAt,
                'recorded_at' => now(),
            ],
        );
    }
}
