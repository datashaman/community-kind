<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseNoteStatus;
use Database\Factories\CaseNoteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $service_case_id
 * @property ClassifiedValue $encrypted_content
 * @property CaseNoteStatus $status
 * @property int $version
 * @property string|null $corrects_note_id
 * @property Carbon|null $finalized_at
 * @property-read ServiceCase $serviceCase
 */
class CaseNote extends Model
{
    /** @use HasFactory<CaseNoteFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(function (CaseNote $note): void {
            if ($note->getRawOriginal('status') === CaseNoteStatus::Finalized->value) {
                throw new LogicException('Finalized case notes cannot be overwritten. Add a correction or addendum.');
            }
        });
        static::deleting(fn () => throw new LogicException('Case notes are retained as history.'));
    }

    /** @return BelongsTo<ServiceCase, $this> */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    /** @return BelongsTo<CaseNote, $this> */
    public function correctedNote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_note_id');
    }

    protected function casts(): array
    {
        return ['encrypted_content' => ClassifiedValueCast::class.':case_note', 'status' => CaseNoteStatus::class, 'finalized_at' => 'datetime'];
    }
}
