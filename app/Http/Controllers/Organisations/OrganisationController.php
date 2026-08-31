<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Organisations\CreateOrganisation;
use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Actions\Organisations\TransitionOrganisationStatus;
use App\Enums\OrganisationOwnershipTransferStatus;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationStatus;
use App\Enums\PartyKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\DeleteOrganisationRequest;
use App\Http\Requests\Organisations\SaveOrganisationRequest;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\OrganisationInvitationRoleAssignment;
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
            ->with(['user', 'personParty', 'roleAssignments.program', 'holds'])
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
                'isSynthetic' => $organisation->is_synthetic,
                'demoGeneration' => $organisation->demo_generation,
            ],
            'members' => $memberships
                ->map(function (Membership $membership) use ($organisation, $permissions, $user) {
                    $member = $membership->user;

                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'avatar' => $member->avatar ?? null,
                        'person_party' => [
                            'id' => $membership->personParty->id,
                            'display_name' => $membership->personParty->display_name,
                        ],
                        'role_assignments' => $membership->roleAssignments
                            ->whereNull('ended_at')
                            ->map(fn ($assignment) => [
                                'id' => $assignment->id,
                                'role' => $assignment->role->value,
                                'role_label' => $assignment->role->label(),
                                'program_id' => $assignment->program_id,
                                'scope_label' => $assignment->program_id === null
                                    ? __('Organisation-wide')
                                    : $assignment->program->name,
                            ])
                            ->values(),
                        'role' => $membership->role?->value,
                        'role_label' => $membership->role?->label() ?? __('No operational role'),
                        'is_owner' => $membership->is_owner,
                        'program_ids' => $membership->programs->sortBy('name')->pluck('id')->values()->all(),
                        'can_manage_roles' => $permissions->canUpdateMember
                            && ($user->ownsOrganisation($organisation) || ! $member->is($user)),
                        'can_manage_hold' => $permissions->canUpdateMember
                            && ! $member->is($user)
                            && (! $membership->is_owner || $user->ownsOrganisation($organisation)),
                        'hold' => $membership->holds
                            ->first(fn ($hold) => $hold->isActive())
                            ?->only(['id', 'reason', 'review_at', 'expires_at']),
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
                ->with(['personParty', 'roleAssignments.program'])
                ->get()
                ->map(fn ($invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'person_name' => $invitation->person_party_id === null
                        ? $invitation->new_person_name
                        : $invitation->personParty->display_name,
                    'offers_ownership' => $invitation->offers_ownership,
                    'role_assignments' => $invitation->roleAssignments
                        ->map($this->toInvitationRoleAssignment(...))
                        ->values()
                        ->all(),
                    'created_at' => $invitation->created_at->toISOString(),
                ]),
            'permissions' => $permissions,
            'availableRoles' => collect(OrganisationRole::assignable())
                ->when(
                    ! $user->ownsOrganisation($organisation),
                    fn ($roles) => $roles->reject(fn (array $role) => $role['value'] === OrganisationRole::OrganisationAdministrator->value),
                )
                ->values(),
            'personParties' => $organisation->parties()
                ->where('kind', PartyKind::Person->value)
                ->orderBy('display_name')
                ->get(['id', 'display_name']),
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
                $membership->is_owner && ! $organisation->hasOtherCapableOwner($membership),
                403,
                __('The last organisation owner cannot leave.'),
            );

            $wasCurrentOrganisation = $user->isCurrentOrganisation($organisation);
            $fallbackOrganisation = $wasCurrentOrganisation
                ? $user->fallbackOrganisation($organisation)
                : null;

            $membership->end();

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

    /** @return array{role: string, role_label: string, program_id: int|null, scope_label: string} */
    private function toInvitationRoleAssignment(OrganisationInvitationRoleAssignment $assignment): array
    {
        $programId = $assignment->program_id;

        return [
            'role' => $assignment->role->value,
            'role_label' => $assignment->role->label(),
            'program_id' => $programId,
            'scope_label' => $programId === null ? 'Organisation-wide' : $assignment->program->name,
        ];
    }
}
