<?php

namespace App;

use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

class OrganisationStorage
{
    public function __construct(
        private OrganisationContext $organisationContext,
        private ResolveOrganisationAccess $resolveOrganisationAccess,
    ) {}

    public function path(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '..')) {
            throw new InvalidArgumentException('Organisation file paths must be relative and cannot traverse directories.');
        }

        return 'organisations/'.$this->organisationContext->organisation()->id.'/'.$path;
    }

    public function put(string $path, string $contents): string
    {
        $organisation = $this->organisationContext->organisation();

        if ($this->resolveOrganisationAccess->handle($organisation, OrganisationAccessScope::Files) !== OrganisationAccessLevel::Full) {
            throw new LogicException('The Organisation context cannot write tenant files.');
        }

        $path = $this->path($path);

        if (! Storage::put($path, $contents)) {
            throw new RuntimeException('Unable to write the Organisation file.');
        }

        return $path;
    }
}
