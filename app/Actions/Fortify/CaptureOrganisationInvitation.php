<?php

namespace App\Actions\Fortify;

use App\Models\OrganisationInvitation;
use Closure;
use Illuminate\Http\Request;

class CaptureOrganisationInvitation
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->session()->forget('auth.pending_organisation_invitation_id');

        $token = $request->string('invitation')->toString();
        $invitation = $token !== '' ? OrganisationInvitation::findByToken($token) : null;

        if ($invitation?->isPending()) {
            $request->session()->put('auth.pending_organisation_invitation_id', $invitation->id);
        }

        return $next($request);
    }
}
