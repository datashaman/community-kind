<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use App\Models\SandboxPair;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceDemoSandbox
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $pairId = $request->session()->get('demo_sandbox_pair_id');

        if (! is_string($pairId)) {
            return $next($request);
        }

        $pair = SandboxPair::query()->find($pairId);

        if ($pair === null || ! $pair->status->isAccessible() || $pair->expires_at->isPast()
            || $pair->generation !== $request->session()->get('demo_sandbox_generation')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(410, 'This demo sandbox has expired.');
        }

        if ($request->routeIs('demo.*')) {
            return $next($request);
        }

        $organisation = $request->route('current_organisation')
            ?? $request->route('organisation')
            ?? $request->route('public_organisation');

        if (is_string($organisation)) {
            $organisation = Organisation::query()->where('slug', $organisation)->first();
        }

        if ($organisation instanceof Organisation) {
            abort_unless($organisation->sandbox_pair_id === $pair->id && $organisation->is_synthetic, 404);
            $sessionGeneration = $request->session()->get('demo_organisation_generation');

            if (is_int($sessionGeneration)) {
                abort_unless($organisation->demo_generation === $sessionGeneration, 410);
            }
        }

        $blockedRoutes = [
            'cases.documents.*',
            'supporter-journeys.dispatch',
            'invitations.*',
            'organisations.invitations.*',
            'organisations.members.*',
            'organisations.lifecycle.*',
            'organisations.slug.*',
            'organisations.ownership-transfers.*',
            'organisations.destroy',
            'organisations.store',
            'organisations.leave',
            'profile.update',
            'profile.destroy',
            'user-password.update',
            'two-factor.*',
            'security.recovery-codes.*',
            'security.other-browser-sessions.*',
        ];

        foreach ($blockedRoutes as $route) {
            abort_if($request->routeIs($route), 403, 'This capability is disabled in demo sandboxes.');
        }

        return $next($request);
    }
}
