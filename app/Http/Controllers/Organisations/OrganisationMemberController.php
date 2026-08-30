<?php

namespace App\Http\Controllers\Organisations;

use App\Enums\OrganisationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\UpdateOrganisationMemberRequest;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OrganisationMemberController extends Controller
{
    /**
     * Update the specified organisation member's role.
     */
    public function update(UpdateOrganisationMemberRequest $request, Organisation $organisation, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $organisation);

        DB::transaction(function () use ($request, $organisation, $user) {
            $membership = $organisation->memberships()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $membership->update([
                'role' => OrganisationRole::from($request->validated('role')),
            ]);

            if ($request->has('program_ids')) {
                $membership->programs()->sync($request->validated('program_ids', []));
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('organisations.edit', ['organisation' => $organisation->slug]);
    }

    /**
     * Remove the specified organisation member.
     */
    public function destroy(Organisation $organisation, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $organisation);

        abort_if($organisation->owner()?->is($user), 403, __('The organisation owner cannot be removed.'));

        $organisation->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentOrganisation($organisation)) {
            $fallbackOrganisation = $user->fallbackOrganisation($organisation);

            if ($fallbackOrganisation) {
                $user->switchOrganisation($fallbackOrganisation);
            } else {
                $user->update(['current_organisation_id' => null]);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('organisations.edit', ['organisation' => $organisation->slug]);
    }
}
