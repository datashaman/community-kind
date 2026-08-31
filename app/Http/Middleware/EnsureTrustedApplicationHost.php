<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrustedApplicationHost
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $platformDomain = config('organisations.platform_domain');
        $routeName = $request->route()?->getName();

        if (is_string($platformDomain) && $request->getHost() === $platformDomain) {
            return $next($request);
        }

        if (is_string($routeName) && (str_starts_with($routeName, 'public.') || str_starts_with($routeName, 'portal.'))) {
            return $next($request);
        }

        abort(Response::HTTP_NOT_FOUND);
    }
}
