<?php

namespace App\Console\Commands;

use App\Actions\Security\RunIncidentExercise;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('security:incident:exercise {--actor-user= : Optional User ID recorded as the synthetic exercise actor}')]
#[Description('Exercise all synthetic incident scenarios and write a redacted signed evidence pack')]
class ExerciseIncidentResponse extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RunIncidentExercise $exercise): int
    {
        $actorId = $this->option('actor-user');
        $actor = $actorId === null ? null : User::query()->findOrFail((int) $actorId);
        $result = $exercise->handle($actor);
        $this->info("Incident exercise {$result['exerciseId']} completed; redacted evidence pack {$result['path']} ({$result['digest']}).");

        return self::SUCCESS;
    }
}
