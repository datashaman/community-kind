<?php

namespace App\Http\Middleware;

use App\Actions\Portal\ResolvePortalAccess;
use App\Models\Organisation;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class EnsurePortalAccess
{
    public function __construct(private readonly ResolvePortalAccess $resolvePortalAccess) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organisation = $request->attributes->get('public_organisation');
        $grantId = $request->session()->get('portal_access_grant_id');
        $version = $request->session()->get('portal_access_version');
        $user = $request->user();

        if (! $organisation instanceof Organisation || ! is_string($grantId) || ! is_int($version) || $user === null) {
            $this->endSession($request);
            abort(404);
        }

        try {
            $grant = $this->resolvePortalAccess->handle($grantId, $version, $organisation, $user);
        } catch (ModelNotFoundException|HttpExceptionInterface $exception) {
            $this->endSession($request);

            throw $exception;
        }
        $request->attributes->set('portal_access_grant', $grant);
        $request->attributes->set('portal_party', $grant->personParty);

        return $next($request);
    }

    private function endSession(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
