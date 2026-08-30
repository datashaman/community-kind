<?php

namespace App\Actions\Organisations;

use App\Models\OrganisationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptOrganisationInvitation
{
    public function handle(User $user, OrganisationInvitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation): void {
            $invitation = OrganisationInvitation::query()
                ->with('organisation')
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if (! $invitation->isPending() || ! $invitation->isFor($user)) {
                throw ValidationException::withMessages([
                    'invitation' => __('This invitation is invalid or unavailable.'),
                ]);
            }

            $invitation->organisation->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role],
            );

            $invitation->update(['accepted_at' => now()]);
            $user->switchOrganisation($invitation->organisation);
        });
    }
}
