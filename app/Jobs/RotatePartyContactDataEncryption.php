<?php

namespace App\Jobs;

use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Actions\Parties\RotatePartyContactDataEncryption as RotatePartyContactDataEncryptionAction;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Models\Organisation;
use App\OrganisationContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LogicException;

class RotatePartyContactDataEncryption implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $organisationId;

    public int $accessVersion;

    public function __construct(Organisation $organisation)
    {
        $currentOrganisation = Organisation::query()->findOrFail($organisation->id);
        $this->organisationId = $currentOrganisation->id;
        $this->accessVersion = $currentOrganisation->access_version;
        $this->onQueue('bulk');
    }

    public function handle(
        OrganisationContext $organisationContext,
        ResolveOrganisationAccess $resolveOrganisationAccess,
        RotatePartyContactDataEncryptionAction $rotate,
    ): void {
        $organisation = Organisation::query()->find($this->organisationId);

        if ($organisation === null || $organisation->access_version !== $this->accessVersion) {
            throw new LogicException('The queued Organisation context is stale or unavailable.');
        }

        if ($resolveOrganisationAccess->handle($organisation, OrganisationAccessScope::Jobs) !== OrganisationAccessLevel::Full) {
            throw new LogicException('The Organisation context cannot run tenant jobs.');
        }

        $organisationContext->run($organisation, fn () => $rotate->handle($organisation));
    }
}
