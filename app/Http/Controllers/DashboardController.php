<?php

namespace App\Http\Controllers;

use App\Actions\Reporting\BuildImpactDashboard;
use App\Actions\ServiceMonitoring\BuildServiceOperationsDashboard;
use App\Http\Requests\DashboardMetricsRequest;
use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\OrganisationContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DashboardMetricsRequest $request, Organisation $currentOrganisation, BuildServiceOperationsDashboard $buildDashboard, BuildImpactDashboard $buildImpact): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = OrganisationInvitation::query()
            ->with(['inviter', 'organisation'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(function (OrganisationInvitation $invitation): array {
                app(OrganisationContext::class)->run(
                    $invitation->organisation,
                    fn () => $invitation->load(['personParty', 'roleAssignments.program']),
                );

                return [
                    'id' => $invitation->id,
                    'inviterName' => $invitation->inviter->name,
                    'personName' => $invitation->person_party_id === null
                        ? $invitation->new_person_name
                        : $invitation->personParty->display_name,
                    'offersOwnership' => $invitation->offers_ownership,
                    'roleAssignments' => $invitation->roleAssignments->map(fn ($assignment) => [
                        'roleLabel' => $assignment->role->label(),
                        'scopeLabel' => $assignment->program_id === null
                            ? 'Organisation-wide'
                            : $assignment->program->name,
                    ])->values()->all(),
                    'organisation' => [
                        'name' => $invitation->organisation->name,
                        'slug' => $invitation->organisation->slug,
                    ],
                ];
            });

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'serviceOperations' => $buildDashboard->handle(
                $request->user(),
                $currentOrganisation,
                $request->filled('program_id') ? $request->integer('program_id') : null,
            ),
            'impact' => $buildImpact->handle($request->user(), $currentOrganisation, $request->validated()),
        ]);
    }
}
