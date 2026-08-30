<?php

namespace App\Console\Commands;

use App\Actions\Organisations\TransitionOrganisationStatus;
use App\Enums\OrganisationStatus;
use App\Models\Organisation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('organisations:purge-scheduled')]
#[Description('Soft-delete Organisations whose recovery period has ended')]
class PurgeScheduledOrganisations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TransitionOrganisationStatus $transitionOrganisationStatus): int
    {
        Organisation::query()
            ->where('status', OrganisationStatus::ScheduledForDeletion)
            ->where('deletion_scheduled_for', '<=', now())
            ->chunkById(100, function ($organisations) use ($transitionOrganisationStatus): void {
                foreach ($organisations as $organisation) {
                    $transitionOrganisationStatus->handle($organisation, OrganisationStatus::Deleted);
                }
            });

        return self::SUCCESS;
    }
}
