<?php

namespace App\Actions\Organisations;

use App\Enums\PartyKind;
use App\Models\Membership;
use App\Models\OrganisationInvitation;
use App\Models\Party;
use App\Models\User;
use App\OrganisationContext;
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

            if (! $user->hasVerifiedEmail() || ! $invitation->isPending() || ! $invitation->isFor($user)) {
                throw ValidationException::withMessages([
                    'invitation' => __('This invitation is invalid or unavailable.'),
                ]);
            }

            if ($invitation->organisation->memberships()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'invitation' => __('You already have an active membership in this organisation.'),
                ]);
            }

            app(OrganisationContext::class)->run($invitation->organisation, function () use ($user, $invitation): void {
                $invitation->load('roleAssignments');
                $party = $this->resolveParty($invitation);
                $initialAssignments = $invitation->roleAssignments;

                $membership = Membership::query()->create([
                    'organisation_id' => $invitation->organisation_id,
                    'user_id' => $user->id,
                    'person_party_id' => $party->id,
                    'is_owner' => $invitation->offers_ownership,
                    'role' => null,
                    'accepted_at' => now(),
                ]);

                if ($initialAssignments->isEmpty()) {
                    $membership->roleAssignments()->create([
                        'organisation_id' => $invitation->organisation_id,
                        'role' => $invitation->role,
                    ]);
                } else {
                    $membership->roleAssignments()->createMany(
                        $initialAssignments->map(fn ($assignment) => [
                            'organisation_id' => $invitation->organisation_id,
                            'role' => $assignment->role,
                            'program_id' => $assignment->program_id,
                        ])->all(),
                    );
                }

                $membership->update([
                    'role' => $initialAssignments->isEmpty()
                        ? $invitation->role
                        : $initialAssignments->first()->role,
                ]);
            });

            $invitation->update(['accepted_at' => now()]);
            $user->switchOrganisation($invitation->organisation);
        });
    }

    private function resolveParty(OrganisationInvitation $invitation): Party
    {
        if ($invitation->person_party_id !== null) {
            return Party::query()
                ->whereKey($invitation->person_party_id)
                ->where('kind', PartyKind::Person->value)
                ->firstOrFail();
        }

        if ($invitation->new_person_name === null) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation does not specify a person Party.'),
            ]);
        }

        return Party::query()->create([
            'kind' => PartyKind::Person,
            'display_name' => $invitation->new_person_name,
        ]);
    }
}
