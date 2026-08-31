<?php

namespace App\Console\Commands;

use App\Actions\Volunteering\ExpireVolunteerCredentials as ExpireCredentials;
use App\Enums\OrganisationStatus;
use App\Models\Organisation;
use App\OrganisationContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('volunteers:expire-credentials')]
#[Description('Expire dated volunteer credentials and record tenant audit evidence')]
class ExpireVolunteerCredentials extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OrganisationContext $context, ExpireCredentials $expireCredentials): int
    {
        $expired = 0;
        Organisation::query()->where('status', OrganisationStatus::Active)->eachById(function (Organisation $organisation) use ($context, $expireCredentials, &$expired): void {
            $expired += $context->run($organisation, fn (): int => $expireCredentials->handle($organisation));
        });
        $this->info("Expired {$expired} volunteer credential(s).");

        return self::SUCCESS;
    }
}
