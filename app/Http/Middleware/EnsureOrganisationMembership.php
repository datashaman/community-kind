<?php

namespace App\Http\Middleware;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganisationMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        [$user, $organisation] = [$request->user(), $this->organisation($request)];

        abort_if(! $user || ! $organisation || ! $user->belongsToOrganisation($organisation), 403);

        $this->ensureOrganisationMemberHasRequiredRole($user, $organisation, $minimumRole);

        if ($request->route('current_organisation') && ! $user->isCurrentOrganisation($organisation)) {
            $user->switchOrganisation($organisation);
        }

        return $next($request);
    }

    /**
     * Ensure the given user has at least the given role, if applicable.
     */
    protected function ensureOrganisationMemberHasRequiredRole(User $user, Organisation $organisation, ?string $minimumRole): void
    {
        if ($minimumRole === null) {
            return;
        }

        $requiredRole = OrganisationRole::tryFrom($minimumRole);

        abort_if(
            $requiredRole === null ||
            ! $user->hasOrganisationRole($organisation, $requiredRole),
            403,
        );
    }

    /**
     * Get the organisation associated with the request.
     */
    protected function organisation(Request $request): ?Organisation
    {
        $organisation = $request->route('current_organisation') ?? $request->route('organisation');

        if (is_string($organisation)) {
            $organisation = Organisation::where('slug', $organisation)->first();
        }

        return $organisation;
    }
}
