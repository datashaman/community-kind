<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Security\RecordPlatformSecurityEvent;
use App\Actions\Security\RevokeOtherBrowserSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RevokeOtherBrowserSessionsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OtherBrowserSessionController extends Controller
{
    public function __invoke(
        RevokeOtherBrowserSessionsRequest $request,
        RevokeOtherBrowserSessions $revokeOtherBrowserSessions,
        RecordPlatformSecurityEvent $recordPlatformSecurityEvent,
    ): RedirectResponse {
        $user = $request->user();
        $revokedCount = $revokeOtherBrowserSessions->handle($user, $request->session()->getId());

        $recordPlatformSecurityEvent->handle(
            type: 'other_browser_sessions_revoked',
            actor: $user,
            subject: $user,
            metadata: ['revoked_count' => $revokedCount],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Other browser sessions revoked.'),
        ]);

        return back();
    }
}
