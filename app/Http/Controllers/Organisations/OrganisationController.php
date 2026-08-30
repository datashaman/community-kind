<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Organisations\CreateOrganisation;
use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Actions\Organisations\TransitionOrganisationStatus;
use App\Enums\OrganisationOwnershipTransferStatus;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\DeleteOrganisationRequest;
use App\Http\Requests\Organisations\SaveOrganisationRequest;
use App\Models\Membership;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganisationController extends Controller
{
    /**
     * Display a listing of the user's organisations.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('organisations/index', [
            'organisations' => $user->toUserOrganisations(includeCurrent: true),
        ]);
    }

    /**
     * Store a newly created organisation.
     */
    public function store(SaveOrganisationRequest $request, CreateOrganisation $createOrganisation): RedirectResponse
    {
        $organisation = $createOrganisation->handle($request->user(), $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organisation created.')]);

        return to_route('organisations.edit', ['organisation' => $organisation->slug]);
    }

    /**
     * Show the organisation edit page.
     */
    public function edit(
        Request $request,
        Organisation $organisation,
        ResolveOrganisationAccess $resolveOrganisationAccess,
    ): Response {
        $user = $request->user();
        $permissions = $user->toOrganisationPermissions($organisation)->constrainedByAccess(
            canAdminister: $resolveOrganisationAccess->allowsStaffCapability($organisation, $user, 'administration'),
            canRecover: $resolveOrganisationAccess->allowsStaffCapability($organisation, $user, 'recovery'),
        );
        $memberships = $organisation->memberships()
            ->with(['user', 'programs'])
            ->get();
        $pendingOwnershipTransfer = $organisation->ownershipTransfers()
            ->with(['nominatedBy', 'nominee'])
            ->where('status', OrganisationOwnershipTransferStatus::Pending)
            ->where('expires_at', '>', now())
            ->where(fn ($query) => $query
                ->where('nominated_by_user_id', $user->id)
                ->orWhere('nominee_user_id', $user->id))
            ->first();

        return Inertia::render('organisations/edit', [
            'organisation' => [
                'id' => $organisation->id,
                'name' => $organisation->name,
                'slug' => $organisation->slug,
                'status' => $organisation->status->value,
            ],
            'members' => $memberships
                ->map(function (Membership $membership) {
                    $member = $membership->user;

                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'avatar' => $member->avatar ?? null,
                        'role' => $membership->role?->value,
                        'role_label' => $membership->role?->label() ?? __('No operational role'),
                        'is_owner' => $membership->is_owner,
                        'program_ids' => $membership->programs->sortBy('name')->pluck('id')->values()->all(),
                    ];
                }),
            'programs' => $organisation->programs()
                ->orderBy('name')
                ->get(['id', 'name']),
            'invitations' => $organisation->invitations()
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where(fn ($query) => $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now()))
                ->get()
                ->map(fn ($invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'created_at' => $invitation->created_at->toISOString(),
                ]),
            'permissions' => $permissions,
            'availableRoles' => OrganisationRole::assignable(),
            'allowedTransitions' => $user->ownsOrganisation($organisation)
                ? collect($organisation->status->allowedTransitions())
                    ->reject(fn (OrganisationStatus $status) => in_array($status, [
                        OrganisationStatus::ScheduledForDeletion,
                        OrganisationStatus::Deleted,
                    ], true))
                    ->map(fn (OrganisationStatus $status) => $status->value)
                    ->values()
                : [],
            'ownerCandidates' => $user->ownsOrganisation($organisation)
                ? $memberships
                    ->reject(fn (Membership $membership) => $membership->is_owner)
                    ->map(fn (Membership $membership) => [
                        'id' => $membership->user->id,
                        'name' => $membership->user->name,
                    ])
                    ->values()
                : [],
            'ownershipTransfer' => $pendingOwnershipTransfer === null ? null : [
                'id' => $pendingOwnershipTransfer->id,
                'nomineeUserId' => $pendingOwnershipTransfer->nominee_user_id,
                'nomineeName' => $pendingOwnershipTransfer->nominee->name,
                'nominatedByName' => $pendingOwnershipTransfer->nominatedBy->name,
                'expiresAt' => $pendingOwnershipTransfer->expires_at->toISOString(),
                'canAccept' => $pendingOwnershipTransfer->nominee_user_id === $user->id,
            ],
            'accessHolds' => $organisation->accessHolds()
                ->whereNull('released_at')
                ->where('starts_at', '<=', now())
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($hold) => [
                    'id' => $hold->id,
                    'reason' => $hold->reason,
                    'scope' => $hold->scope->value,
                    'accessLevel' => $hold->access_level->value,
                    'reviewAt' => $hold->review_at->toISOString(),
                    'expiresAt' => $hold->expires_at?->toISOString(),
                ]),
        ]);
    }

    /**
     * Update the specified organisation.
     */
    public function update(SaveOrganisationRequest $request, Organisation $organisation): RedirectResponse
    {
        Gate::authorize('update', $organisation);

        $organisation = DB::transaction(function () use ($request, $organisation) {
            $organisation = Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();

            $organisation->update(['name' => $request->validated('name')]);

            return $organisation;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organisation updated.')]);

        return to_route('organisations.edit', ['organisation' => $organisation->slug]);
    }

    /**
     * Switch the user's current organisation.
     */
    public function switch(Request $request, Organisation $organisation): RedirectResponse
    {
        abort_unless($request->user()->belongsToOrganisation($organisation), 403);

        $request->user()->switchOrganisation($organisation);

        return to_route('dashboard', ['current_organisation' => $organisation->slug]);
    }

    /**
     * Leave the specified organisation.
     */
    public function leave(Request $request, Organisation $organisation): RedirectResponse
    {
        Gate::authorize('leave', $organisation);

        $user = $request->user();
        DB::transaction(function () use ($organisation, $user): void {
            $organisation = Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            $membership = $organisation->memberships()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $membership->is_owner && $organisation->owners()->count() <= 1,
                403,
                __('The last organisation owner cannot leave.'),
            );

            $wasCurrentOrganisation = $user->isCurrentOrganisation($organisation);
            $fallbackOrganisation = $wasCurrentOrganisation
                ? $user->fallbackOrganisation($organisation)
                : null;

            $membership->delete();

            if ($wasCurrentOrganisation) {
                if ($fallbackOrganisation) {
                    $user->switchOrganisation($fallbackOrganisation);
                } else {
                    $user->update(['current_organisation_id' => null]);
                    $user->unsetRelation('currentOrganisation');
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the organisation ":name"', ['name' => $organisation->name])]);

        return to_route('organisations.index');
    }

    /**
     * Delete the specified organisation.
     */
    public function destroy(
        DeleteOrganisationRequest $request,
        Organisation $organisation,
        TransitionOrganisationStatus $transitionOrganisationStatus,
    ): RedirectResponse {
        $transitionOrganisationStatus->handle(
            $organisation,
            OrganisationStatus::ScheduledForDeletion,
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organisation scheduled for deletion after a 30-day recovery period.'),
        ]);

        return to_route('organisations.edit', $organisation);
    }
}
