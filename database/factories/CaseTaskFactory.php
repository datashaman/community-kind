<?php

namespace Database\Factories;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseTaskStatus;
use App\Models\CaseTask;
use App\Models\ServiceCase;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CaseTask> */
class CaseTaskFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => Str::uuid()->toString(), 'organisation_id' => app(OrganisationContext::class)->id(), 'service_case_id' => ServiceCase::factory(), 'type' => 'content', 'encrypted_content' => new ClassifiedValue(json_encode(['title' => fake()->sentence(), 'details' => fake()->paragraph()], JSON_THROW_ON_ERROR)), 'data_key_version' => app(ClassifiedDataEncrypter::class)->currentVersion(), 'status' => CaseTaskStatus::Open, 'version' => 1, 'due_at' => now()->addWeek()];
    }
}
