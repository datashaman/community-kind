<?php

namespace App\Http\Controllers\Organisations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\CreateMembershipHoldRequest;
use App\Models\MembershipHold;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MembershipHoldController extends Controller
{
    public function store(CreateMembershipHoldRequest $request, Organisation $organisation, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $organisation);

        DB::transaction(function () use ($request, $organisation, $user): void {
            $membership = $organisation->memberships()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($request->user()->is($user), 403, __('You cannot place your own membership on hold.'));
            abort_if($membership->isHeld(), 422, __('This membership already has an active hold.'));
            abort_if(
                $membership->is_owner && ! $request->user()->ownsOrganisation($organisation),
                403,
                __('Only an organisation owner can place another owner on hold.'),
            );

            if ($membership->is_owner) {
                $organisation->memberships()->where('is_owner', true)->lockForUpdate()->get();

                abort_if(! $organisation->hasOtherCapableOwner($membership), 403, __('The last capable organisation owner cannot be placed on hold.'));
            }

            $membership->holds()->create([
                'organisation_id' => $organisation->id,
                'reason' => $request->validated('reason'),
                'starts_at' => now(),
                'review_at' => $request->date('review_at'),
                'expires_at' => $request->date('expires_at'),
                'issued_by' => $request->user()->id,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership placed on hold.')]);

        return to_route('organisations.edit', $organisation);
    }

    public function destroy(Request $request, Organisation $organisation, User $user, int $hold): RedirectResponse
    {
        Gate::authorize('updateMember', $organisation);

        DB::transaction(function () use ($request, $organisation, $user, $hold): void {
            $membership = $organisation->memberships()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $hold = MembershipHold::query()->lockForUpdate()->findOrFail($hold);

            abort_unless($hold->membership_id === $membership->id, 404);
            abort_if($request->user()->is($user), 403, __('You cannot release your own membership hold.'));
            abort_if($hold->released_at !== null, 422, __('This Membership Hold has already been released.'));

            $hold->update([
                'released_at' => now(),
                'released_by' => $request->user()->id,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership hold released.')]);

        return to_route('organisations.edit', $organisation);
    }
}
