<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Security\RecordPlatformSecurityEvent;
use App\Actions\Security\RevokeOtherBrowserSessions;
use App\Enums\PlatformSecurityEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RevokeOtherBrowserSessionsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OtherBrowserSessionController extends Controller
{
    public function __invoke(
        RevokeOtherBrowserSessionsRequest $request,
        RevokeOtherBrowserSessions $revokeOtherBrowserSessions,
        RecordPlatformSecurityEvent $recordPlatformSecurityEvent,
    ): RedirectResponse {
        $user = $request->user();
        DB::transaction(function () use ($request, $user, $revokeOtherBrowserSessions, $recordPlatformSecurityEvent): void {
            $revokedCount = $revokeOtherBrowserSessions->handle($user, $request->session()->getId());

            $recordPlatformSecurityEvent->handle(
                type: PlatformSecurityEventType::OtherBrowserSessionsRevoked,
                metadata: ['revoked_count' => $revokedCount],
                actor: $user,
                subject: $user,
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Other browser sessions revoked.'),
        ]);

        return back();
    }
}
