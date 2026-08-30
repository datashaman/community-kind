<?php

namespace Database\Factories;

use App\Enums\OrganisationOwnershipTransferStatus;
use App\Models\Organisation;
use App\Models\OrganisationOwnershipTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationOwnershipTransfer>
 */
class OrganisationOwnershipTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'nominated_by_user_id' => User::factory(),
            'nominee_user_id' => User::factory(),
            'status' => OrganisationOwnershipTransferStatus::Pending,
            'expires_at' => now()->addHours(72),
        ];
    }
}
