<?php

namespace App\Actions\Programs;

use App\Actions\Organisations\RecordOrganisationLifecycleEvent;
use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Enums\OrganisationLifecycleEventType;
use App\Models\Program;
use App\OrganisationContext;
use App\OrganisationStorage;
use LogicException;

class ExportPrograms
{
    public function __construct(
        private OrganisationContext $organisationContext,
        private OrganisationStorage $organisationStorage,
        private RecordOrganisationLifecycleEvent $recordOrganisationLifecycleEvent,
        private ResolveOrganisationAccess $resolveOrganisationAccess,
    ) {}

    public function handle(): string
    {
        $organisation = $this->organisationContext->organisation();

        if ($this->resolveOrganisationAccess->handle($organisation, OrganisationAccessScope::Exports)->rank() > OrganisationAccessLevel::ReadOnly->rank()) {
            throw new LogicException('The Organisation context cannot export tenant data.');
        }

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create the Program export.');
        }

        fputcsv($stream, ['id', 'name', 'slug']);
        Program::query()->orderBy('id')->each(fn (Program $program) => fputcsv($stream, [
            $program->id,
            $program->name,
            $program->slug,
        ]));
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new \RuntimeException('Unable to read the Program export.');
        }

        $path = $this->organisationStorage->put('exports/programs.csv', $contents);
        $this->recordOrganisationLifecycleEvent->handle(
            $organisation,
            OrganisationLifecycleEventType::ProgramExportGenerated,
            metadata: ['path' => $path],
        );

        return $path;
    }
}
