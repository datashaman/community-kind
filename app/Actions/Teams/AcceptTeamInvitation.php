<?php

namespace App\Actions\Teams;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptTeamInvitation
{
    public function handle(User $user, TeamInvitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation): void {
            $invitation = TeamInvitation::query()
                ->with('team')
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if (! $invitation->isPending() || ! $invitation->isFor($user)) {
                throw ValidationException::withMessages([
                    'invitation' => __('This invitation is invalid or unavailable.'),
                ]);
            }

            $invitation->team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role],
            );

            $invitation->update(['accepted_at' => now()]);
            $user->switchTeam($invitation->team);
        });
    }
}
