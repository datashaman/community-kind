<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseClassification;
use App\Enums\CaseDocumentState;
use Database\Factories\CaseDocumentVersionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $case_document_id
 * @property int $generation
 * @property CaseClassification $classification
 * @property ClassifiedValue $encrypted_display_name
 * @property CaseDocumentState $state
 * @property string|null $quarantine_path
 * @property string|null $object_key
 * @property string|null $detected_mime
 * @property int|null $byte_size
 * @property ClassifiedValue|null $encrypted_sha256
 * @property string|null $scanner_engine_version
 * @property string|null $scanner_signature_version
 * @property string|null $result_category
 * @property Carbon|null $scan_started_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property-read CaseDocument $document
 */
class CaseDocumentVersion extends Model
{
    /** @use HasFactory<CaseDocumentVersionFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    /** @return BelongsTo<CaseDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(CaseDocument::class, 'case_document_id');
    }

    protected function casts(): array
    {
        return [
            'state' => CaseDocumentState::class,
            'classification' => CaseClassification::class,
            'encrypted_display_name' => ClassifiedValueCast::class.':case_document_version.display_name',
            'encrypted_sha256' => ClassifiedValueCast::class.':case_document_version.sha256',
            'generation' => 'integer',
            'byte_size' => 'integer',
            'scan_started_at' => 'datetime',
            'scanned_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
