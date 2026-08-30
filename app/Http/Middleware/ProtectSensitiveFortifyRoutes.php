<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectSensitiveFortifyRoutes
{
    public function __construct(
        private EnsureStaffSecurityRequirements $ensureStaffSecurityRequirements,
        private EnsureRecentPassword $ensureRecentPassword,
        private EnsureRecentMfa $ensureRecentMfa,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->route()?->getName(), [
            'two-factor.disable',
            'two-factor.regenerate-recovery-codes',
        ], true)) {
            return $next($request);
        }

        return $this->ensureStaffSecurityRequirements->handle(
            $request,
            fn (Request $request): Response => $this->ensureRecentPassword->handle(
                $request,
                fn (Request $request): Response => $this->ensureRecentMfa->handle($request, $next),
            ),
        );
    }
}
