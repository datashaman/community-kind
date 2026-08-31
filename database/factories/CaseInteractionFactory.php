<?php

namespace Database\Factories;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Models\CaseInteraction;
use App\Models\ServiceCase;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CaseInteraction> */
class CaseInteractionFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => Str::uuid()->toString(), 'organisation_id' => app(OrganisationContext::class)->id(), 'service_case_id' => ServiceCase::factory(), 'interaction_type' => 'telephone', 'type' => 'content', 'encrypted_content' => new ClassifiedValue(json_encode(['summary' => fake()->sentence()], JSON_THROW_ON_ERROR)), 'data_key_version' => app(ClassifiedDataEncrypter::class)->currentVersion(), 'occurred_at' => now(), 'recorded_at' => now()];
    }
}
