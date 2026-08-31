<?php

namespace Database\Factories;

use App\Enums\DuplicateReviewDecision;
use App\Models\IntakeRequest;
use App\Models\Party;
use App\Models\PartyDuplicateReview;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyDuplicateReview>
 */
class PartyDuplicateReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => app(OrganisationContext::class)->id(),
            'intake_request_id' => IntakeRequest::factory(),
            'submitted_party_id' => fn (array $attributes): int => IntakeRequest::query()->where('id', $attributes['intake_request_id'])->firstOrFail()->party_id,
            'candidate_party_id' => Party::factory()->state(['organisation_id' => app(OrganisationContext::class)->id()]),
            'matched_fields' => ['email'],
            'decision' => DuplicateReviewDecision::Pending,
        ];
    }
}
