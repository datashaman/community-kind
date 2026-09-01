<?php

namespace App\Http\Controllers\Demo;

use App\Actions\Demo\ExpireSandboxPair;
use App\Actions\Demo\ProvisionSandboxPair;
use App\Http\Controllers\Controller;
use App\Models\SandboxBootstrapToken;
use App\Models\SandboxPair;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SandboxController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        abort_unless(config('demo_sandbox.enabled'), 404);

        if ($this->hasActiveSandbox($request)) {
            return to_route('demo.personas.index');
        }

        if (is_string($token = $this->pendingToken($request))) {
            return to_route('demo.bootstrap', ['token' => $token])->withHeaders($this->privateHeaders());
        }

        return Inertia::render('demo/start', [
            'lifetimeHours' => (int) config('demo_sandbox.maximum_lifetime_hours'),
        ]);
    }

    public function store(Request $request, ProvisionSandboxPair $provisionSandboxPair): RedirectResponse
    {
        abort_unless(config('demo_sandbox.enabled'), 404);

        if ($this->hasActiveSandbox($request)) {
            return to_route('demo.personas.index');
        }

        if (is_string($token = $this->pendingToken($request))) {
            return to_route('demo.bootstrap', ['token' => $token])->withHeaders($this->privateHeaders());
        }

        return $this->provision($request, $provisionSandboxPair);
    }

    public function destroy(
        Request $request,
        ExpireSandboxPair $expireSandboxPair,
        ProvisionSandboxPair $provisionSandboxPair,
    ): RedirectResponse {
        abort_unless(config('demo_sandbox.enabled'), 404);
        $pairId = $request->session()->get('demo_sandbox_pair_id')
            ?? $request->session()->get('demo_pending_pair_id');

        if (is_string($pairId)) {
            $pair = SandboxPair::query()->find($pairId);

            if ($pair?->status->isAccessible()) {
                $expireSandboxPair->handle($pair);
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->provision($request, $provisionSandboxPair);
    }

    private function provision(Request $request, ProvisionSandboxPair $provisionSandboxPair): RedirectResponse
    {
        try {
            $result = $provisionSandboxPair->handle();
        } catch (Throwable $exception) {
            report($exception);

            return to_route('demo.create')->withErrors([
                'demo' => 'The demo could not be prepared. Please try again.',
            ]);
        }

        $request->session()->put([
            'demo_pending_pair_id' => $result['pair']->id,
            'demo_pending_token' => Crypt::encryptString($result['token']),
        ]);

        return to_route('demo.bootstrap', ['token' => $result['token']])->withHeaders($this->privateHeaders());
    }

    private function pendingToken(Request $request): ?string
    {
        $pairId = $request->session()->get('demo_pending_pair_id');
        $encryptedToken = $request->session()->get('demo_pending_token');

        if (! is_string($pairId) || ! is_string($encryptedToken)) {
            return null;
        }

        try {
            $token = Crypt::decryptString($encryptedToken);
        } catch (DecryptException) {
            $this->forgetPending($request);

            return null;
        }

        $bootstrap = SandboxBootstrapToken::query()
            ->where('sandbox_pair_id', $pairId)
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->first();
        $pair = SandboxPair::query()->find($pairId);

        if ($bootstrap === null || $pair === null || $bootstrap->expires_at->isPast()
            || $pair->expires_at->isPast() || $bootstrap->generation !== $pair->generation
            || ! $pair->status->isAccessible()) {
            $this->forgetPending($request);

            return null;
        }

        return $token;
    }

    private function hasActiveSandbox(Request $request): bool
    {
        $pairId = $request->session()->get('demo_sandbox_pair_id');
        $generation = $request->session()->get('demo_sandbox_generation');

        if (! is_string($pairId) || ! is_int($generation)) {
            return false;
        }

        return SandboxPair::query()
            ->whereKey($pairId)
            ->where('generation', $generation)
            ->whereIn('status', ['ready', 'active'])
            ->where('expires_at', '>', now())
            ->exists();
    }

    private function forgetPending(Request $request): void
    {
        $request->session()->forget(['demo_pending_pair_id', 'demo_pending_token']);
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
