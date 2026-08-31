<?php

namespace Database\Factories;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseAppointmentStatus;
use App\Models\CaseAppointment;
use App\Models\ServiceCase;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CaseAppointment> */
class CaseAppointmentFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => Str::uuid()->toString(), 'organisation_id' => app(OrganisationContext::class)->id(), 'service_case_id' => ServiceCase::factory(), 'type' => 'content', 'encrypted_content' => new ClassifiedValue(json_encode(['summary' => fake()->sentence(), 'location' => fake()->streetAddress()], JSON_THROW_ON_ERROR)), 'data_key_version' => app(ClassifiedDataEncrypter::class)->currentVersion(), 'status' => CaseAppointmentStatus::Scheduled, 'version' => 1, 'scheduled_at' => now()->addWeek()];
    }
}
