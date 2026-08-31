<?php

namespace Database\Factories;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\ExternalReferralStatus;
use App\Models\ExternalReferral;
use App\Models\ServiceCase;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ExternalReferral> */
class ExternalReferralFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => Str::uuid()->toString(), 'organisation_id' => app(OrganisationContext::class)->id(), 'service_case_id' => ServiceCase::factory(), 'type' => 'content', 'encrypted_content' => new ClassifiedValue(json_encode(['destination' => fake()->company(), 'purpose' => fake()->sentence(), 'minimum_necessary' => fake()->sentence()], JSON_THROW_ON_ERROR)), 'data_key_version' => app(ClassifiedDataEncrypter::class)->currentVersion(), 'status' => ExternalReferralStatus::Draft, 'sharing_authority' => 'service_consent', 'version' => 1];
    }
}
