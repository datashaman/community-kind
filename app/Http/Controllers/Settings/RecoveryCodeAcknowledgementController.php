<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AcknowledgeRecoveryCodesRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RecoveryCodeAcknowledgementController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AcknowledgeRecoveryCodesRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'recovery_codes_acknowledged_at' => now(),
        ])->save();
        $request->session()->put('auth.mfa_confirmed_at', now()->getTimestamp());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Recovery codes acknowledged.'),
        ]);

        return to_route('security.edit');
    }
}
