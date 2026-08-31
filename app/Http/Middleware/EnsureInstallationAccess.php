<?php

namespace App\Http\Middleware;

use App\Enums\InstallationCapability;
use App\Models\InstallationControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallationAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $capability = null): Response
    {
        $routeName = $request->route()?->getName();

        if (! $request->isMethodSafe()
            && ! $this->isSecurityWrite($routeName)
            && InstallationControl::isPaused(InstallationCapability::Writes)) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Installation writes are temporarily unavailable.');
        }

        if ($capability === InstallationCapability::Forms->value
            && ! $request->isMethodSafe()
            && InstallationControl::isPaused(InstallationCapability::Forms)) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Forms are temporarily unavailable.');
        }

        if ($request->allFiles() !== [] && InstallationControl::isPaused(InstallationCapability::Uploads)) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Uploads are temporarily unavailable.');
        }

        return $next($request);
    }

    private function isSecurityWrite(?string $routeName): bool
    {
        if ($routeName === null) {
            return false;
        }

        return $routeName === 'logout'
            || $routeName === 'security.other-browser-sessions.destroy'
            || $routeName === 'user-password.update'
            || str_starts_with($routeName, 'login.')
            || str_starts_with($routeName, 'password.')
            || str_starts_with($routeName, 'two-factor.')
            || str_starts_with($routeName, 'mfa.');
    }
}
