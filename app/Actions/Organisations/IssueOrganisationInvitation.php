<?php

namespace App\Actions\Organisations;

use App\Data\IssuedOrganisationInvitation;
use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Str;

class IssueOrganisationInvitation
{
    public function handle(Organisation $organisation, User $inviter, string $email, OrganisationRole $role): IssuedOrganisationInvitation
    {
        $token = Str::random(64);

        $invitation = $organisation->invitations()->create([
            'token_hash' => hash('sha256', $token),
            'email' => Str::lower($email),
            'role' => $role,
            'invited_by' => $inviter->id,
            'expires_at' => now()->addHours(72),
        ]);

        return new IssuedOrganisationInvitation($invitation, $token);
    }
}
