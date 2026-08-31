<?php

namespace Database\Factories;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Models\CaseOutcome;
use App\Models\ServiceCase;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CaseOutcome> */
class CaseOutcomeFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => Str::uuid()->toString(), 'organisation_id' => app(OrganisationContext::class)->id(), 'service_case_id' => ServiceCase::factory(), 'measures' => ['progress' => 3], 'type' => 'content', 'encrypted_content' => new ClassifiedValue(fake()->paragraph()), 'data_key_version' => app(ClassifiedDataEncrypter::class)->currentVersion(), 'effective_at' => now(), 'recorded_at' => now()];
    }
}
