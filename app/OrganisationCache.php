<?php

namespace App;

use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use Closure;
use Illuminate\Support\Facades\Cache;
use LogicException;

class OrganisationCache
{
    public function __construct(
        private OrganisationContext $organisationContext,
        private ResolveOrganisationAccess $resolveOrganisationAccess,
    ) {}

    public function key(string $key): string
    {
        $organisation = $this->organisationContext->organisation();

        if ($this->resolveOrganisationAccess->handle($organisation, OrganisationAccessScope::Cache)->rank() > OrganisationAccessLevel::ReadOnly->rank()) {
            throw new LogicException('The Organisation context cannot access cached tenant data.');
        }

        return "organisation:{$organisation->id}:v{$organisation->access_version}:{$key}";
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $key, int $seconds, Closure $callback): mixed
    {
        return Cache::remember($this->key($key), $seconds, $callback);
    }
}
