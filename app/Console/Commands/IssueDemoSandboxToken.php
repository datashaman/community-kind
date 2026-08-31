<?php

namespace App\Console\Commands;

use App\Actions\Demo\CreateSandboxBootstrapToken;
use App\Models\SandboxPair;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:sandbox:token {pair : Sandbox pair UUID}')]
#[Description('Issue a replacement token for an unexpired demo sandbox')]
class IssueDemoSandboxToken extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CreateSandboxBootstrapToken $createToken): int
    {
        if (! config('demo_sandbox.enabled')) {
            $this->error('Demo sandboxes are disabled in this environment.');

            return self::FAILURE;
        }

        $pair = SandboxPair::query()->findOrFail($this->argument('pair'));

        if (! $pair->status->isAccessible() || $pair->expires_at->isPast()) {
            $this->error('The sandbox pair is not accessible.');

            return self::FAILURE;
        }

        $this->line(route('demo.bootstrap', ['token' => $createToken->handle($pair)]));

        return self::SUCCESS;
    }
}
