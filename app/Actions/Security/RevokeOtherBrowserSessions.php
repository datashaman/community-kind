<?php

namespace App\Actions\Security;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;

class RevokeOtherBrowserSessions
{
    public function __construct(private ConnectionInterface $database) {}

    public function handle(User $user, string $currentSessionId): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return $this->database
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
