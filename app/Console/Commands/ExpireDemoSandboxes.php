<?php

namespace App\Console\Commands;

use App\Actions\Demo\ExpireSandboxPair;
use App\Enums\SandboxPairStatus;
use App\Models\SandboxPair;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:sandbox:expire')]
#[Description('Expire due demo sandboxes and terminate their sessions')]
class ExpireDemoSandboxes extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ExpireSandboxPair $expire): int
    {
        SandboxPair::query()
            ->whereIn('status', [SandboxPairStatus::Ready, SandboxPairStatus::Active])
            ->where('expires_at', '<=', now())
            ->eachById(fn (SandboxPair $pair) => $expire->handle($pair));

        return self::SUCCESS;
    }
}
