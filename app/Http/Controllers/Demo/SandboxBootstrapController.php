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
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SandboxBootstrapController extends Controller
{
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        abort_unless(config('demo_sandbox.enabled'), 404);
        $tokenHash = hash('sha256', $token);
        $bootstrap = SandboxBootstrapToken::query()->where('token_hash', $tokenHash)->firstOrFail();
        $pair = SandboxPair::query()->findOrFail($bootstrap->sandbox_pair_id);

        if ($bootstrap->used_at !== null && $this->hasActiveSession($request, $pair, $bootstrap)) {
            return to_route('demo.personas.index');
        }

        $this->ensureAvailable($bootstrap, $pair);

        $response = Inertia::render('demo/bootstrap', [
            'token' => $token,
            'expiresAt' => $bootstrap->expires_at->toIso8601String(),
            'expiresAtLabel' => $bootstrap->expires_at->format('j M Y, H:i T'),
        ])->toResponse($request);

        foreach ($this->privateHeaders() as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        abort_unless(config('demo_sandbox.enabled'), 404);
        $tokenHash = hash('sha256', $token);
        $pairId = SandboxBootstrapToken::query()->where('token_hash', $tokenHash)->value('sandbox_pair_id');
        abort_unless(is_string($pairId), 404);

        $bootstrap = DB::transaction(function () use ($pairId, $request, $tokenHash): SandboxBootstrapToken {
            $pair = SandboxPair::query()->lockForUpdate()->findOrFail($pairId);
            $bootstrap = SandboxBootstrapToken::query()
                ->where('sandbox_pair_id', $pair->id)
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->firstOrFail();

            if ($bootstrap->used_at !== null && $this->hasActiveSession($request, $pair, $bootstrap)) {
                return $bootstrap;
            }

            $this->ensureAvailable($bootstrap, $pair);

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

        return to_route('demo.personas.index')->withHeaders($this->privateHeaders());
    }

    private function ensureAvailable(SandboxBootstrapToken $bootstrap, SandboxPair $pair): void
    {
        abort_if($bootstrap->used_at !== null || $bootstrap->revoked_at !== null, 410);
        abort_if($bootstrap->expires_at->isPast() || $pair->expires_at->isPast(), 410);
        abort_unless($bootstrap->generation === $pair->generation && $pair->status->isAccessible(), 410);
    }

    private function hasActiveSession(Request $request, SandboxPair $pair, SandboxBootstrapToken $bootstrap): bool
    {
        return $request->session()->get('demo_sandbox_pair_id') === $pair->id
            && $request->session()->get('demo_sandbox_generation') === $bootstrap->generation
            && $bootstrap->generation === $pair->generation
            && $pair->status->isAccessible()
            && ! $pair->expires_at->isPast();
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return [
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store',
        ];
    }
}
