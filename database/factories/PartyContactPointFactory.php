<?php

namespace Database\Factories;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Cryptography\ContactBlindIndexer;
use App\Data\Values\ClassifiedValue;
use App\Enums\PartyContactType;
use App\Models\Party;
use App\Models\PartyContactPoint;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyContactPoint>
 */
class PartyContactPointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organisation = app(OrganisationContext::class)->organisation();
        $type = PartyContactType::Email;
        $value = fake()->safeEmail();
        $blindIndexer = app(ContactBlindIndexer::class);
        $indexes = $blindIndexer->indexesForWrite($organisation->uuid, $type, $value);
        $currentVersion = $blindIndexer->currentVersion();
        $previousVersion = $blindIndexer->previousVersion();
        $contactPoint = new PartyContactPoint;

        return [
            'id' => $contactPoint->newUniqueId(),
            'organisation_id' => $organisation->id,
            'party_id' => Party::factory()->state(['organisation_id' => $organisation->id]),
            'type' => $type,
            'encrypted_value' => new ClassifiedValue($value),
            'data_key_version' => app(ClassifiedDataEncrypter::class)->currentVersion(),
            'current_index_key_version' => $currentVersion,
            'current_blind_index' => $indexes[$currentVersion],
            'previous_index_key_version' => $previousVersion,
            'previous_blind_index' => $previousVersion === null ? null : $indexes[$previousVersion],
        ];
    }
}
