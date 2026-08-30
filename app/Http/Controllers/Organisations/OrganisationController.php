<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Organisations\CreateOrganisation;
use App\Enums\OrganisationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\DeleteOrganisationRequest;
use App\Http\Requests\Organisations\SaveOrganisationRequest;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\User;
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
    public function edit(Request $request, Organisation $organisation): Response
    {
        $user = $request->user();

        return Inertia::render('organisations/edit', [
            'organisation' => [
                'id' => $organisation->id,
                'name' => $organisation->name,
                'slug' => $organisation->slug,
                'status' => $organisation->status->value,
            ],
            'members' => $organisation->memberships()
                ->with(['user', 'programs'])
                ->get()
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
            'permissions' => $user->toOrganisationPermissions($organisation),
            'availableRoles' => OrganisationRole::assignable(),
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
        $wasCurrentOrganisation = $user->isCurrentOrganisation($organisation);

        $fallbackOrganisation = $wasCurrentOrganisation
            ? $user->fallbackOrganisation($organisation)
            : null;

        $organisation->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($wasCurrentOrganisation) {
            if ($fallbackOrganisation) {
                $user->switchOrganisation($fallbackOrganisation);
            } else {
                $user->update(['current_organisation_id' => null]);
                $user->unsetRelation('currentOrganisation');
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the organisation ":name"', ['name' => $organisation->name])]);

        return to_route('organisations.index');
    }

    /**
     * Delete the specified organisation.
     */
    public function destroy(DeleteOrganisationRequest $request, Organisation $organisation): RedirectResponse
    {
        $user = $request->user();
        $wasCurrentOrganisation = $user->isCurrentOrganisation($organisation);
        $fallbackOrganisation = $wasCurrentOrganisation
            ? $user->fallbackOrganisation($organisation)
            : null;

        DB::transaction(function () use ($user, $organisation) {
            User::where('current_organisation_id', $organisation->id)
                ->where('id', '!=', $user->id)
                ->each(function (User $affectedUser) use ($organisation) {
                    $fallbackOrganisation = $affectedUser->fallbackOrganisation($organisation);

                    if ($fallbackOrganisation) {
                        $affectedUser->switchOrganisation($fallbackOrganisation);
                    } else {
                        $affectedUser->update(['current_organisation_id' => null]);
                    }
                });

            $organisation->invitations()->delete();
            $organisation->memberships()->delete();
            $organisation->delete();
        });

        if ($wasCurrentOrganisation) {
            if ($fallbackOrganisation) {
                $user->switchOrganisation($fallbackOrganisation);
            } else {
                $user->update(['current_organisation_id' => null]);
                $user->unsetRelation('currentOrganisation');
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organisation deleted.')]);

        return to_route('organisations.index');
    }
}
