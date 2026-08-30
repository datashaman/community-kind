<?php

namespace App\Actions\Programs;

use App\Actions\Organisations\RecordOrganisationLifecycleEvent;
use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Enums\OrganisationLifecycleEventType;
use App\Models\Program;
use App\OrganisationCache;
use App\OrganisationContext;
use LogicException;

class BuildProgramReport
{
    public function __construct(
        private OrganisationContext $organisationContext,
        private OrganisationCache $organisationCache,
        private RecordOrganisationLifecycleEvent $recordOrganisationLifecycleEvent,
        private ResolveOrganisationAccess $resolveOrganisationAccess,
    ) {}

    /** @return array{organisation_id: int, program_count: int, program_names: array<int, string>} */
    public function handle(): array
    {
        $organisation = $this->organisationContext->organisation();

        if ($this->resolveOrganisationAccess->handle($organisation, OrganisationAccessScope::Reports)->rank() > OrganisationAccessLevel::ReadOnly->rank()) {
            throw new LogicException('The Organisation context cannot report on tenant data.');
        }

        $report = $this->organisationCache->remember('reports:programs', 300, fn (): array => [
            'organisation_id' => $organisation->id,
            'program_count' => Program::query()->count(),
            'program_names' => Program::query()->orderBy('name')->pluck('name')->all(),
        ]);

        $this->recordOrganisationLifecycleEvent->handle(
            $organisation,
            OrganisationLifecycleEventType::ProgramReportGenerated,
            metadata: ['program_count' => $report['program_count']],
        );

        return $report;
    }
}
