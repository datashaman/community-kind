<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Reporting\BuildImpactDashboard;
use App\Enums\TenantAuditEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardMetricsRequest;
use App\Models\Organisation;
use Illuminate\Http\Response;
use LogicException;

class ImpactChartExportController extends Controller
{
    public function __invoke(
        DashboardMetricsRequest $request,
        Organisation $currentOrganisation,
        BuildImpactDashboard $buildDashboard,
        RecordTenantAuditEvent $recordAudit,
    ): Response {
        $dashboard = $buildDashboard->handle($request->user(), $currentOrganisation, $request->validated());
        $metrics = $dashboard['metrics'];
        abort_if($metrics === [], 403);
        if (! is_array($metrics)) {
            throw new LogicException('The impact dashboard metrics must be an array.');
        }

        $dashboardFilters = $dashboard['filters'];
        if (! is_array($dashboardFilters)) {
            throw new LogicException('The impact dashboard filters must be an array.');
        }
        $filters = array_filter($dashboardFilters, fn (mixed $value): bool => filled($value));
        $recordAudit->handle($currentOrganisation, TenantAuditEventType::ImpactReportExported, 'organisation', (string) $currentOrganisation->id, [
            'registry_version' => $dashboard['registryVersion'],
            'filters_hash' => hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR)),
            'metric_count' => count($metrics),
            'format' => 'svg',
        ], $request->user());

        return response($this->renderChart($dashboard, $metrics, $filters), headers: [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="fictional-impact-chart.svg"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<int, mixed>  $metrics
     * @param  array<array-key, mixed>  $filters
     */
    private function renderChart(array $dashboard, array $metrics, array $filters): string
    {
        $rows = [];
        $maximumByUnit = [];

        foreach ($metrics as $metric) {
            if (! is_array($metric) || ! is_array($metric['definition'] ?? null)) {
                throw new LogicException('Every impact metric must contain a definition.');
            }

            $definition = $metric['definition'];
            $availability = (string) ($metric['availability'] ?? 'unavailable');
            $value = is_numeric($metric['value'] ?? null) ? (float) $metric['value'] : null;
            $unit = (string) ($definition['unit'] ?? '');
            if ($availability === 'available' && $value !== null) {
                $maximumByUnit[$unit] = max($maximumByUnit[$unit] ?? 1.0, abs($value));
            }
            $rows[] = [
                'label' => (string) ($definition['label'] ?? 'Metric'),
                'definition' => sprintf(
                    '%s v%s — %s',
                    (string) ($definition['id'] ?? ''),
                    (string) ($definition['version'] ?? ''),
                    (string) ($definition['formula'] ?? ''),
                ),
                'availability' => $availability,
                'value' => $value,
                'unit' => $unit,
                'display' => $this->metricValue($value, $availability, $unit, (string) $dashboard['currency']),
            ];
        }

        $height = 230 + (count($rows) * 76);
        $description = 'Fictional data. Data as of '.(string) $dashboard['freshAt'].'. '
            .'Period '.(string) $dashboard['period']['start'].' to '.(string) $dashboard['period']['endExclusive'].' exclusive. '
            .'Filters '.json_encode($filters, JSON_THROW_ON_ERROR).'. '
            .implode(' ', array_map(fn (array $row): string => $row['label'].': '.$row['display'].'; '.$row['definition'].'.', $rows));
        $svgRows = [];

        foreach ($rows as $index => $row) {
            $y = 190 + ($index * 76);
            $barWidth = $row['availability'] === 'available' && $row['value'] !== null
                ? max(2, (int) round(abs($row['value']) / $maximumByUnit[$row['unit']] * 520))
                : 0;
            $svgRows[] = sprintf(
                '<g transform="translate(0 %d)"><text x="48" y="0" class="label">%s</text><text x="1152" y="0" text-anchor="end" class="value">%s</text><text x="48" y="22" class="definition">%s</text>%s</g>',
                $y,
                $this->escape($row['label']),
                $this->escape($row['display']),
                $this->escape($row['definition']),
                $barWidth > 0 ? sprintf('<rect x="48" y="34" width="%d" height="18" rx="4" fill="#155e75"/>', $barWidth) : '',
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="%d" viewBox="0 0 1200 %d" role="img" aria-labelledby="title description"><title id="title">CommunityKind impact chart</title><desc id="description">%s</desc><rect width="1200" height="100%%" fill="#ffffff"/><style>.title{font:700 30px system-ui,sans-serif;fill:#172554}.meta{font:16px system-ui,sans-serif;fill:#334155}.label,.value{font:600 18px system-ui,sans-serif;fill:#0f172a}.definition{font:14px system-ui,sans-serif;fill:#475569}</style><text x="48" y="54" class="title">CommunityKind impact chart</text><text x="48" y="86" class="meta">Fictional data · Data as of %s</text><text x="48" y="112" class="meta">Period %s to %s (exclusive)</text><text x="48" y="138" class="meta">Filters: %s</text>%s</svg>',
            $height,
            $height,
            $this->escape($description),
            $this->escape((string) $dashboard['freshAt']),
            $this->escape((string) $dashboard['period']['start']),
            $this->escape((string) $dashboard['period']['endExclusive']),
            $this->escape(json_encode($filters, JSON_THROW_ON_ERROR)),
            implode('', $svgRows),
        );
    }

    private function metricValue(?float $value, string $availability, string $unit, string $currency): string
    {
        if ($availability === 'suppressed') {
            return 'Suppressed';
        }
        if ($availability !== 'available' || $value === null) {
            return 'Unavailable';
        }

        return match ($unit) {
            'currency' => $currency.' '.$value,
            'percent' => $value.'%',
            default => (string) $value,
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
