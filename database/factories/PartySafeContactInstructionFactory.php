<?php

namespace Database\Factories;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Models\Party;
use App\Models\PartySafeContactInstruction;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartySafeContactInstruction>
 */
class PartySafeContactInstructionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $instruction = new PartySafeContactInstruction;
        $organisationId = app(OrganisationContext::class)->id();

        return [
            'id' => $instruction->newUniqueId(),
            'organisation_id' => $organisationId,
            'party_id' => Party::factory()->state(['organisation_id' => $organisationId]),
            'type' => 'instruction',
            'encrypted_value' => new ClassifiedValue('Text messages only.'),
            'data_key_version' => app(ClassifiedDataEncrypter::class)->currentVersion(),
            'source' => 'in_person',
            'effective_at' => now(),
        ];
    }
}
