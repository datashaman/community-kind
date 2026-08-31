<?php

namespace App\Console\Commands;

use App\Actions\Auditing\CreateDailyAuditDigest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('audit:digest:create {date? : Closed UTC day in YYYY-MM-DD format}')]
#[Description('Create a signed, chained audit digest and recovery export for a closed UTC day')]
class CreateAuditDigest extends Command
{
    public function handle(CreateDailyAuditDigest $createDigest): int
    {
        $date = $this->argument('date');
        $manifest = $createDigest->handle($date === null
            ? CarbonImmutable::yesterday('UTC')
            : CarbonImmutable::createFromFormat('!Y-m-d', (string) $date, 'UTC'));
        $this->info("Audit digest {$manifest->manifest_digest} covers {$manifest->manifest_date->format('Y-m-d')} ({$manifest->event_count} events).");

        return self::SUCCESS;
    }
}
