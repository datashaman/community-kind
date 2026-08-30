<?php

namespace App\Actions\Teams;

use App\Data\IssuedTeamInvitation;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;

class IssueTeamInvitation
{
    public function handle(Team $team, User $inviter, string $email, TeamRole $role): IssuedTeamInvitation
    {
        $token = Str::random(64);

        $invitation = $team->invitations()->create([
            'token_hash' => hash('sha256', $token),
            'email' => Str::lower($email),
            'role' => $role,
            'invited_by' => $inviter->id,
            'expires_at' => now()->addHours(72),
        ]);

        return new IssuedTeamInvitation($invitation, $token);
    }
}
