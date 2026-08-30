<?php

namespace App\Actions\Programs;

use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Models\Program;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

class SearchPrograms
{
    public function __construct(
        private OrganisationContext $organisationContext,
        private ResolveOrganisationAccess $resolveOrganisationAccess,
    ) {}

    /** @return Collection<int, Program> */
    public function handle(string $query): Collection
    {
        $organisation = $this->organisationContext->organisation();

        if ($this->resolveOrganisationAccess->handle($organisation, OrganisationAccessScope::Search)->rank() > OrganisationAccessLevel::ReadOnly->rank()) {
            throw new LogicException('The Organisation context cannot search tenant data.');
        }

        return Program::search($query)
            ->where('organisation_id', $organisation->id)
            ->get();
    }
}
