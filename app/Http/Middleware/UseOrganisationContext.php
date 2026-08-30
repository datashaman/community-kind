<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use App\OrganisationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseOrganisationContext
{
    public function __construct(private OrganisationContext $organisationContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organisation = $request->route('current_organisation') ?? $request->route('organisation');

        abort_unless($organisation instanceof Organisation, 404);

        return $this->organisationContext->run($organisation, fn (): Response => $next($request));
    }
}
