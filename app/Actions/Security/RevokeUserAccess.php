<?php

namespace App\Actions\Security;

use App\Models\OrganisationInvitation;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;

class RevokeUserAccess
{
    public function __construct(private ConnectionInterface $database) {}

    /** @return array{session_count: int, invitation_count: int, password_reset_revoked: bool} */
    public function handle(User $user, ?User $actor = null): array
    {
        $sessionCount = $this->database
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->delete();
        $passwordResetRevoked = $this->database
            ->table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete() > 0;
        $invitationCount = OrganisationInvitation::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoked_by' => $actor?->id,
            ]);

        $user->forceFill(['remember_token' => null])->save();

        return [
            'session_count' => $sessionCount,
            'invitation_count' => $invitationCount,
            'password_reset_revoked' => $passwordResetRevoked,
        ];
    }
}
