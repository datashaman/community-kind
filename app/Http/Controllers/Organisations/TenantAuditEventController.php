<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Auditing\BuildTenantAuditView;
use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\TenantAuditEventType;
use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\TenantAuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TenantAuditEventController extends Controller
{
    public function __invoke(
        Request $request,
        Organisation $currentOrganisation,
        BuildTenantAuditView $buildView,
        RecordTenantAuditEvent $recordAudit,
    ): Response {
        Gate::authorize('viewAny', [TenantAuditEvent::class, $currentOrganisation]);
        $events = $buildView->handle($request->user(), $currentOrganisation);
        $role = $request->user()->organisationRole($currentOrganisation);
        $recordAudit->handle($currentOrganisation, TenantAuditEventType::AuditViewAccessed, 'tenant_audit', (string) $currentOrganisation->id, [
            'record_count' => count($events),
            'scope' => 'policy_projection',
            'role' => $role->value,
        ], $request->user());

        return Inertia::render('audit/index', [
            'events' => $events,
            'role' => $role->label(),
        ]);
    }
}
