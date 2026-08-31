<?php

namespace App\Http\Middleware;

use App\Models\AudienceSegment;
use App\Models\Donation;
use App\Models\IntakeRequest;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\Program;
use App\Models\SandboxPair;
use App\Models\SupporterJourney;
use App\Models\TenantAuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if ($request->session()->has('portal_access_grant_id')) {
            return [
                ...parent::share($request),
                'name' => config('app.name'),
                'auth' => ['user' => null],
                'sidebarOpen' => false,
                'canCreateOrganisation' => false,
                'currentOrganisation' => null,
                'organisations' => [],
                'demoSandbox' => null,
                'canViewParties' => false,
                'canViewPrograms' => false,
                'canViewIntakes' => false,
                'canViewDonations' => false,
                'canViewAudienceSegments' => false,
                'canViewSupporterJourneys' => false,
                'canViewAudit' => false,
            ];
        }

        $user = $request->user();
        $organisationData = null;
        $organisations = function () use ($user, &$organisationData): Collection {
            return $organisationData ??= $user?->toUserOrganisations(includeCurrent: true) ?? collect();
        };
        $routeOrganisation = $request->route('current_organisation');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'canCreateOrganisation' => $user?->can('create', Organisation::class) ?? false,
            'currentOrganisation' => fn () => $organisations()->first(fn ($organisation) => $organisation->isCurrent),
            'organisations' => $organisations,
            'demoSandbox' => function () use ($request, $user): ?array {
                $pairId = $request->session()->get('demo_sandbox_pair_id');

                if (is_string($pairId)) {
                    $pair = SandboxPair::query()->find($pairId);

                    if ($pair?->status->isAccessible()) {
                        return [
                            'pairId' => $pair->id,
                            'expiresAt' => $pair->expires_at->toISOString(),
                        ];
                    }
                }

                $visibleOrganisation = $request->route('current_organisation')
                    ?? $request->route('organisation')
                    ?? $user?->currentOrganisation;

                return $visibleOrganisation instanceof Organisation && $visibleOrganisation->is_synthetic
                    ? ['pairId' => null, 'expiresAt' => null]
                    : null;
            },
            'canViewParties' => fn (): bool => $routeOrganisation instanceof Organisation
                && Gate::allows('viewAny', [Party::class, $routeOrganisation]),
            'canViewPrograms' => fn (): bool => $routeOrganisation instanceof Organisation
                && Gate::allows('viewAny', [Program::class, $routeOrganisation]),
            'canViewIntakes' => fn (): bool => $routeOrganisation instanceof Organisation
                && Gate::allows('viewAny', [IntakeRequest::class, $routeOrganisation]),
            'canViewDonations' => fn (): bool => $routeOrganisation instanceof Organisation
                && Gate::allows('viewAny', [Donation::class, $routeOrganisation]),
            'canViewAudienceSegments' => fn (): bool => $routeOrganisation instanceof Organisation
                && Gate::allows('viewAny', [AudienceSegment::class, $routeOrganisation]),
            'canViewSupporterJourneys' => fn (): bool => $routeOrganisation instanceof Organisation
                && Gate::allows('viewAny', [SupporterJourney::class, $routeOrganisation]),
            'canViewAudit' => fn (): bool => $routeOrganisation instanceof Organisation
                && Gate::allows('viewAny', [TenantAuditEvent::class, $routeOrganisation]),
        ];
    }
}
