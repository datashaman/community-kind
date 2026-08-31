<?php

namespace App\Http\Middleware;

use App\Models\AudienceSegment;
use App\Models\Donation;
use App\Models\IntakeRequest;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\Program;
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
        ];
    }
}
