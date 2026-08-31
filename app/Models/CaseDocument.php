<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseClassification;
use Database\Factories\CaseDocumentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $service_case_id
 * @property string $type
 * @property CaseClassification $classification
 * @property ClassifiedValue $encrypted_display_name
 * @property int $generation
 * @property string|null $current_version_id
 * @property-read ServiceCase $serviceCase
 * @property-read CaseDocumentVersion|null $currentVersion
 * @property-read Collection<int, CaseDocumentVersion> $versions
 */
class CaseDocument extends Model
{
    /** @use HasFactory<CaseDocumentFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    /** @return BelongsTo<ServiceCase, $this> */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    /** @return HasMany<CaseDocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(CaseDocumentVersion::class);
    }

    /** @return BelongsTo<CaseDocumentVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CaseDocumentVersion::class, 'current_version_id');
    }

    protected function casts(): array
    {
        return [
            'classification' => CaseClassification::class,
            'encrypted_display_name' => ClassifiedValueCast::class.':case_document.display_name',
            'generation' => 'integer',
        ];
    }
}
