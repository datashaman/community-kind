<?php

namespace App\Console\Commands;

use App\Actions\Organisations\ResolveOrganisationAccess;
use App\Actions\Programs\BuildProgramReport;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Models\Organisation;
use App\OrganisationContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('organisations:program-report {organisation : Organisation slug}')]
#[Description('Generate the scoped Program report for one Organisation')]
class ReportOrganisationPrograms extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(
        OrganisationContext $organisationContext,
        ResolveOrganisationAccess $resolveOrganisationAccess,
        BuildProgramReport $buildProgramReport,
    ): int {
        $organisation = Organisation::query()->where('slug', $this->argument('organisation'))->first();

        if ($organisation === null) {
            $this->error('Organisation not found.');

            return self::FAILURE;
        }

        if ($resolveOrganisationAccess->handle($organisation, OrganisationAccessScope::Commands) !== OrganisationAccessLevel::Full) {
            $this->error('Organisation commands are unavailable for this Organisation.');

            return self::FAILURE;
        }

        $report = $organisationContext->run($organisation, fn (): array => $buildProgramReport->handle());
        $this->line((string) json_encode($report, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
