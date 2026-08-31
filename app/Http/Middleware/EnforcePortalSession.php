<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePortalSession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('portal_access_grant_id') && ! $request->routeIs('portal.*')) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
