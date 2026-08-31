<?php

namespace Database\Factories;

use App\Enums\CaseClassification;
use App\Enums\CaseDocumentState;
use App\Models\CaseDocument;
use App\Models\CaseDocumentVersion;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CaseDocumentVersion>
 */
class CaseDocumentVersionFactory extends Factory
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
            'case_document_id' => CaseDocument::factory()->state(['generation' => 1]),
            'type' => 'case_document_version_security',
            'generation' => 1,
            'classification' => CaseClassification::Confidential,
            'encrypted_display_name' => 'supporting-document.pdf',
            'state' => CaseDocumentState::Quarantined,
            'expires_at' => now()->addDay(),
        ];
    }
}
