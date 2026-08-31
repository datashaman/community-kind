<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\ServiceMonitoring\BuildServiceOperationsDashboard;
use App\Enums\TenantAuditEventType;
use App\Http\Controllers\Controller;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceOperationsExportController extends Controller
{
    public function __invoke(
        Request $request,
        Organisation $currentOrganisation,
        BuildServiceOperationsDashboard $buildDashboard,
        RecordTenantAuditEvent $recordAudit,
    ): StreamedResponse {
        $programId = $request->filled('program_id') ? $request->integer('program_id') : null;
        $dashboard = $buildDashboard->handle($request->user(), $currentOrganisation, $programId);
        $recordCount = collect(['caseload', 'waitlist', 'overdue', 'risks', 'referrals'])->sum(fn (string $key): int => count($dashboard[$key]));
        $recordAudit->handle($currentOrganisation, TenantAuditEventType::ServiceOperationsExported, 'organisation', (string) $currentOrganisation->id, [
            'program_id' => $programId,
            'record_count' => $recordCount,
        ], $request->user());

        return response()->streamDownload(function () use ($dashboard): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fputcsv($stream, ['queue', 'record_id', 'case_id', 'program', 'status', 'date']);

            foreach ($dashboard['caseload'] as $row) {
                fputcsv($stream, ['caseload', $row['id'], $row['id'], $this->spreadsheetSafe($row['program']), $row['status'], '']);
            }

            foreach ($dashboard['waitlist'] as $row) {
                fputcsv($stream, ['waitlist', $row['id'], '', $this->spreadsheetSafe($row['program']), $row['status'], $row['since']]);
            }

            foreach ($dashboard['overdue'] as $row) {
                fputcsv($stream, ['overdue', $row['id'], $row['caseId'], $this->spreadsheetSafe($row['program']), 'open', $row['dueAt']]);
            }

            foreach ($dashboard['risks'] as $row) {
                fputcsv($stream, ['risk', $row['id'], $row['caseId'], $this->spreadsheetSafe($row['program']), 'unresolved', $row['effectiveAt']]);
            }

            foreach ($dashboard['referrals'] as $row) {
                fputcsv($stream, ['referral', $row['id'], $row['caseId'], $this->spreadsheetSafe($row['program']), $row['status'], $row['effectiveAt']]);
            }

            fclose($stream);
        }, 'service-operations.csv', [
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
