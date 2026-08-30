<?php

namespace App\Actions\Fortify;

use App\Models\TeamInvitation;
use Closure;
use Illuminate\Http\Request;

class CaptureTeamInvitation
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->session()->forget('auth.pending_team_invitation_id');

        $token = $request->string('invitation')->toString();
        $invitation = $token !== '' ? TeamInvitation::findByToken($token) : null;

        if ($invitation?->isPending()) {
            $request->session()->put('auth.pending_team_invitation_id', $invitation->id);
        }

        return $next($request);
    }
}
