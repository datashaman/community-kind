<?php

namespace App\Jobs;

use App\Actions\Organisations\RecordOrganisationLifecycleEvent;
use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Enums\OrganisationLifecycleEventType;
use App\Models\Organisation;
use App\Models\Program;
use App\OrganisationContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LogicException;

class RebuildProgramSearchDocument implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 1;

    public function __construct(Program $program)
    {
        $context = app(OrganisationContext::class);
        $context->ensureOwns($program->organisation_id);
        $organisation = $context->organisation();

        $this->organisationId = $organisation->id;
        $this->accessVersion = $organisation->access_version;
        $this->programId = $program->id;
    }

    /**
     * Execute the job.
     */
    public function handle(
        OrganisationContext $organisationContext,
        ResolveOrganisationAccess $resolveOrganisationAccess,
        RecordOrganisationLifecycleEvent $recordOrganisationLifecycleEvent,
    ): void {
        $organisation = Organisation::query()->find($this->organisationId);

        if ($organisation === null || $organisation->access_version !== $this->accessVersion) {
            throw new LogicException('The queued Organisation context is stale or unavailable.');
        }

        if ($resolveOrganisationAccess->handle($organisation, OrganisationAccessScope::Jobs) !== OrganisationAccessLevel::Full) {
            throw new LogicException('The Organisation context cannot run tenant jobs.');
        }

        $organisationContext->run($organisation, function () use ($recordOrganisationLifecycleEvent, $organisation): void {
            $program = Program::query()->findOrFail($this->programId);
            $program->searchable();
            $recordOrganisationLifecycleEvent->handle(
                $organisation,
                OrganisationLifecycleEventType::ProgramSearchRebuilt,
                metadata: ['program_id' => $program->id],
            );
        });
    }

    public int $organisationId;

    public int $accessVersion;

    public int $programId;
}
