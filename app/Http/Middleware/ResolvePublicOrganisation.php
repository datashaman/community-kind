<?php

namespace App\Http\Middleware;

use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Models\Organisation;
use App\Models\OrganisationSlug;
use App\OrganisationContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicOrganisation
{
    public function __construct(
        private ResolveOrganisationAccess $resolveOrganisationAccess,
        private OrganisationContext $organisationContext,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('public_organisation');

        abort_unless($this->isValidPublicSlug($slug), Response::HTTP_NOT_FOUND);

        $organisation = Organisation::query()->where('slug', $slug)->first();

        if ($organisation === null) {
            $previousSlug = OrganisationSlug::query()
                ->with('organisation')
                ->where('slug', $slug)
                ->where('redirect_until', '>', now())
                ->first();
            $organisation = $previousSlug?->organisation;

            abort_unless($this->isPubliclyAvailable($organisation), Response::HTTP_NOT_FOUND);

            return $this->redirectToCanonicalHost($request, $organisation);
        }

        abort_unless($this->isPubliclyAvailable($organisation), Response::HTTP_NOT_FOUND);

        $request->attributes->set('public_organisation', $organisation);

        return $this->organisationContext->run(
            $organisation,
            fn (): Response => $next($request),
        );
    }

    private function isValidPublicSlug(mixed $slug): bool
    {
        return is_string($slug)
            && strlen($slug) <= 63
            && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1
            && ! in_array($slug, (array) config('organisations.reserved_public_subdomains', []), true);
    }

    private function isPubliclyAvailable(?Organisation $organisation): bool
    {
        return $organisation !== null
            && $this->resolveOrganisationAccess->handle($organisation, OrganisationAccessScope::Public)
                === OrganisationAccessLevel::Full;
    }

    private function redirectToCanonicalHost(Request $request, Organisation $organisation): RedirectResponse
    {
        $route = $request->route();
        $routeName = $route?->getName();

        abort_unless(is_string($routeName) && str_starts_with($routeName, 'public.'), Response::HTTP_NOT_FOUND);

        $parameters = $route->parameters();
        $parameters['public_organisation'] = $organisation->slug;
        $url = route($routeName, $parameters);

        if ($request->getQueryString() !== null) {
            $url .= '?'.$request->getQueryString();
        }

        return redirect()->away($url, $request->isMethodSafe() ? 302 : 307);
    }
}
