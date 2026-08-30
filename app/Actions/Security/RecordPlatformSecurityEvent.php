<?php

namespace App\Actions\Security;

use App\Models\PlatformSecurityEvent;
use App\Models\User;

class RecordPlatformSecurityEvent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(string $type, User $actor, User $subject, array $metadata = []): PlatformSecurityEvent
    {
        return PlatformSecurityEvent::create([
            'type' => $type,
            'actor_user_id' => $actor->id,
            'subject_user_id' => $subject->id,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
