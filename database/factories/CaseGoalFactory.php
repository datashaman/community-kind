<?php

namespace Database\Factories;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseGoalStatus;
use App\Models\CaseGoal;
use App\Models\ServiceCase;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CaseGoal> */
class CaseGoalFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => Str::uuid()->toString(), 'organisation_id' => app(OrganisationContext::class)->id(), 'service_case_id' => ServiceCase::factory(), 'type' => 'content', 'encrypted_content' => new ClassifiedValue(json_encode(['title' => fake()->sentence(), 'description' => fake()->paragraph()], JSON_THROW_ON_ERROR)), 'data_key_version' => app(ClassifiedDataEncrypter::class)->currentVersion(), 'status' => CaseGoalStatus::Draft, 'version' => 1, 'target_at' => now()->addMonth()];
    }
}
