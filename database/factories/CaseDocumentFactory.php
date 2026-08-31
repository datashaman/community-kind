<?php

namespace Database\Factories;

use App\Enums\CaseClassification;
use App\Models\CaseDocument;
use App\Models\ServiceCase;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CaseDocument>
 */
class CaseDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'organisation_id' => app(OrganisationContext::class)->id(),
            'service_case_id' => ServiceCase::factory(),
            'type' => 'case_document_name',
            'classification' => CaseClassification::Confidential,
            'encrypted_display_name' => 'supporting-document.pdf',
            'generation' => 0,
        ];
    }
}
