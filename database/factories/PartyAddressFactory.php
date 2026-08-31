<?php

namespace Database\Factories;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Models\Party;
use App\Models\PartyAddress;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyAddress>
 */
class PartyAddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $address = new PartyAddress;
        $organisationId = app(OrganisationContext::class)->id();

        return [
            'id' => $address->newUniqueId(),
            'organisation_id' => $organisationId,
            'party_id' => Party::factory()->state(['organisation_id' => $organisationId]),
            'type' => 'address',
            'label' => 'Home',
            'encrypted_value' => new ClassifiedValue(fake()->address()),
            'data_key_version' => app(ClassifiedDataEncrypter::class)->currentVersion(),
            'service_area' => fake()->city(),
            'country_code' => 'ZA',
        ];
    }
}
