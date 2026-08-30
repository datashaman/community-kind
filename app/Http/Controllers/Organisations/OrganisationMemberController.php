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

            $endedAt = now();
            $membership->roleAssignments()->whereNull('ended_at')->update(['ended_at' => $endedAt]);

            $assignments = collect($request->roleAssignments());
            $membership->roleAssignments()->createMany(
                $assignments->map(fn (array $assignment) => [
                    'organisation_id' => $organisation->id,
                    'role' => OrganisationRole::from($assignment['role']),
                    'program_id' => $assignment['program_id'],
                ])->all(),
            );

            $membership->update([
                'role' => $assignments->first() === null
                    ? null
                    : OrganisationRole::from($assignments->first()['role']),
            ]);
            $membership->programs()->sync(
                $assignments->pluck('program_id')->filter()->unique()->values()->all(),
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member roles updated.')]);

        return to_route('organisations.edit', ['organisation' => $organisation->slug]);
    }

    /**
     * Remove the specified organisation member.
     */
    public function destroy(Organisation $organisation, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $organisation);

        DB::transaction(function () use ($organisation, $user): void {
            $organisation = Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            $membership = $organisation->memberships()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $membership->is_owner && ! $organisation->hasOtherCapableOwner($membership),
                403,
                __('The last organisation owner cannot be removed.'),
            );

            $membership->end();

            if ($user->isCurrentOrganisation($organisation)) {
                $fallbackOrganisation = $user->fallbackOrganisation($organisation);

                if ($fallbackOrganisation) {
                    $user->switchOrganisation($fallbackOrganisation);
                } else {
                    $user->update(['current_organisation_id' => null]);
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('organisations.edit', ['organisation' => $organisation->slug]);
    }
}
