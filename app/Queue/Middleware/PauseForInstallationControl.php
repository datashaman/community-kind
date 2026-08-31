<?php

namespace App\Queue\Middleware;

use App\Enums\InstallationCapability;
use App\Models\InstallationControl;
use Closure;

class PauseForInstallationControl
{
    public function handle(object $job, Closure $next): void
    {
        if (InstallationControl::isPaused(InstallationCapability::Queues)) {
            if (method_exists($job, 'release')) {
                $job->release(60);
            }

            return;
        }

        $next($job);
    }
}
