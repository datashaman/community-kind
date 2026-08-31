<?php

namespace App\Console\Commands;

use App\Actions\Demo\ProvisionSandboxPair;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:sandbox:provision {--hours=24 : Lifetime from one to 24 hours}')]
#[Description('Provision an isolated synthetic demo sandbox pair')]
class ProvisionDemoSandbox extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ProvisionSandboxPair $provision): int
    {
        if (! config('demo_sandbox.enabled')) {
            $this->error('Demo sandboxes are disabled in this environment.');

            return self::FAILURE;
        }

        $result = $provision->handle((int) $this->option('hours'));
        $this->info('Sandbox pair: '.$result['pair']->id);
        $this->line(route('demo.bootstrap', ['token' => $result['token']]));

        return self::SUCCESS;
    }
}
