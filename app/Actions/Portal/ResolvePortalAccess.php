<?php

namespace App\Actions\Portal;

use App\Models\Organisation;
use App\Models\PortalAccessGrant;
use App\Models\User;

final class ResolvePortalAccess
{
    public function handle(string $grantId, int $accessVersion, Organisation $organisation, User $user): PortalAccessGrant
    {
        $grant = PortalAccessGrant::query()
            ->with(['personParty', 'user'])
            ->whereKey($grantId)
            ->where('organisation_id', $organisation->id)
            ->where('user_id', $user->id)
            ->where('access_version', $accessVersion)
            ->firstOrFail();

        abort_unless($grant->hasActiveAccess(), 410, 'This portal access is no longer available.');

        return $grant;
    }
}
