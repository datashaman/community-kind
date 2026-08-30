<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetOrganisationUrlDefaults
{
    /**
     * Set the default URL parameters for organisation-based routes.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($currentOrganisation = $request->user()?->currentOrganisation) {
            URL::defaults([
                'current_organisation' => $currentOrganisation->slug,
                'organisation' => $currentOrganisation->slug,
            ]);
        }

        return $next($request);
    }
}
