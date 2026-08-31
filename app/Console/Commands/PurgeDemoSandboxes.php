<?php

namespace App\Console\Commands;

use App\Actions\Demo\PurgeSandboxPair;
use App\Enums\SandboxPairStatus;
use App\Models\SandboxPair;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:sandbox:purge {pair? : Limit the purge to one sandbox pair UUID}')]
#[Description('Idempotently purge expired or failed demo sandboxes')]
class PurgeDemoSandboxes extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PurgeSandboxPair $purge): int
    {
        SandboxPair::query()
            ->when($this->argument('pair'), fn ($query, string $pair) => $query->whereKey($pair))
            ->whereIn('status', [SandboxPairStatus::Expired, SandboxPairStatus::Failed, SandboxPairStatus::Purging])
            ->eachById(fn (SandboxPair $pair) => $purge->handle($pair));

        return self::SUCCESS;
    }
}
