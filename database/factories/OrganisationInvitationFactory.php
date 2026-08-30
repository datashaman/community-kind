<?php

namespace Database\Factories;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrganisationInvitation>
 */
class OrganisationInvitationFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (OrganisationInvitation $invitation): void {
            app(OrganisationContext::class)->run($invitation->organisation, function () use ($invitation): void {
                if ($invitation->roleAssignments()->doesntExist()) {
                    $invitation->roleAssignments()->create([
                        'organisation_id' => $invitation->organisation_id,
                        'role' => $invitation->role,
                    ]);
                }
            });
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(64);

        return [
            'token_hash' => hash('sha256', $token),
            'organisation_id' => Organisation::factory(),
            'email' => fake()->unique()->safeEmail(),
            'new_person_name' => fake()->name(),
            'role' => OrganisationRole::CaseWorker,
            'invited_by' => User::factory(),
            'expires_at' => null,
            'accepted_at' => null,
        ];
    }

    /**
     * Indicate that the invitation has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that the invitation has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }

    public function forToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => hash('sha256', $token),
        ]);
    }

    /**
     * Indicate that the invitation expires in the given time.
     */
    public function expiresIn(int $value, string $unit = 'days'): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->add($unit, $value),
        ]);
    }
}
