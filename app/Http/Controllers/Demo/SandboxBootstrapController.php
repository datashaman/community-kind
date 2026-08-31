<?php

namespace App\Http\Controllers\Demo;

use App\Enums\SandboxPairStatus;
use App\Http\Controllers\Controller;
use App\Models\SandboxBootstrapToken;
use App\Models\SandboxPair;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SandboxBootstrapController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        abort_unless(config('demo_sandbox.enabled'), 404);
        $tokenHash = hash('sha256', $token);
        $pairId = SandboxBootstrapToken::query()->where('token_hash', $tokenHash)->value('sandbox_pair_id');
        abort_unless(is_string($pairId), 404);

        $bootstrap = DB::transaction(function () use ($pairId, $tokenHash): SandboxBootstrapToken {
            $pair = SandboxPair::query()->lockForUpdate()->findOrFail($pairId);
            $bootstrap = SandboxBootstrapToken::query()
                ->where('sandbox_pair_id', $pair->id)
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($bootstrap->used_at !== null || $bootstrap->revoked_at !== null, 410);
            abort_if($bootstrap->expires_at->isPast() || $pair->expires_at->isPast(), 410);
            abort_unless($bootstrap->generation === $pair->generation && $pair->status->isAccessible(), 410);

            $bootstrap->update(['used_at' => now()]);

            if ($pair->status === SandboxPairStatus::Ready) {
                $pair->update(['status' => SandboxPairStatus::Active, 'activated_at' => now()]);
            }

            return $bootstrap;
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put([
            'demo_sandbox_pair_id' => $bootstrap->sandbox_pair_id,
            'demo_sandbox_generation' => $bootstrap->generation,
        ]);

        return to_route('demo.personas.index')->withHeaders([
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store',
        ]);
    }
}
