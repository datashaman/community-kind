<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Reporting\BuildImpactDashboard;
use App\Enums\TenantAuditEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardMetricsRequest;
use App\Models\Organisation;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImpactReportExportController extends Controller
{
    public function __invoke(
        DashboardMetricsRequest $request,
        Organisation $currentOrganisation,
        BuildImpactDashboard $buildDashboard,
        RecordTenantAuditEvent $recordAudit,
    ): StreamedResponse {
        $dashboard = $buildDashboard->handle($request->user(), $currentOrganisation, $request->validated());
        abort_if($dashboard['metrics'] === [], 403);
        $dashboardFilters = $dashboard['filters'];
        if (! is_array($dashboardFilters)) {
            throw new LogicException('The impact dashboard filters must be an array.');
        }
        $filters = array_filter($dashboardFilters, fn (mixed $value): bool => filled($value));
        $recordAudit->handle($currentOrganisation, TenantAuditEventType::ImpactReportExported, 'organisation', (string) $currentOrganisation->id, [
            'registry_version' => $dashboard['registryVersion'],
            'filters_hash' => hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR)),
            'metric_count' => count($dashboard['metrics']),
            'format' => 'csv',
        ], $request->user());

        return response()->streamDownload(function () use ($dashboard, $filters): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fputcsv($stream, ['category', 'metric', 'value', 'availability', 'unit', 'definition_id', 'definition_version', 'formula', 'period_start', 'period_end_exclusive', 'timezone', 'currency', 'fictional_data', 'fresh_at', 'filters']);
            foreach ($dashboard['metrics'] as $metric) {
                fputcsv($stream, [
                    $metric['definition']['category'],
                    $this->spreadsheetSafe($metric['definition']['label']),
                    $metric['value'],
                    $metric['availability'],
                    $metric['definition']['unit'],
                    $metric['definition']['id'],
                    $metric['definition']['version'],
                    $this->spreadsheetSafe($metric['definition']['formula']),
                    $dashboard['period']['start'],
                    $dashboard['period']['endExclusive'],
                    $dashboard['timezone'],
                    $dashboard['currency'],
                    'Fictional data',
                    $dashboard['freshAt'],
                    json_encode($filters, JSON_THROW_ON_ERROR),
                ]);
            }
            fclose($stream);
        }, 'fictional-impact-report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^\s*[=+\-@]/u', $value) === 1 ? "'{$value}" : $value;
    }
}
