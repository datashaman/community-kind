<?php

namespace App\Http\Middleware;

use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganisationAccess
{
    public function __construct(private ResolveOrganisationAccess $resolveOrganisationAccess) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $capability = 'read'): Response
    {
        $organisation = $request->route('current_organisation') ?? $request->route('organisation');

        if (is_string($organisation)) {
            $organisation = Organisation::where('slug', $organisation)->first();
        }

        abort_unless($organisation instanceof Organisation, 404);

        abort_unless(
            $this->resolveOrganisationAccess->allowsStaffCapability($organisation, $request->user(), $capability),
            403,
        );

        return $next($request);
    }
}
