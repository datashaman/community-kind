<?php

namespace App\Console\Commands;

use App\Actions\Auditing\VerifyAuditDigestChain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('audit:digest:verify')]
#[Description('Verify chained audit manifests, recovery exports, signatures, and live event reconciliation')]
class VerifyAuditDigests extends Command
{
    public function handle(VerifyAuditDigestChain $verifyDigests): int
    {
        $result = $verifyDigests->handle();
        if (! $result['valid']) {
            foreach ($result['failures'] as $failure) {
                $this->error($failure['date'].': '.$failure['reason']);
            }

            return self::FAILURE;
        }

        $this->info('Audit digest chain verified.');

        return self::SUCCESS;
    }
}
