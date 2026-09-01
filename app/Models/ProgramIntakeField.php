<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\ProgramIntakeFieldType;
use Database\Factories\ProgramIntakeFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $program_id
 * @property string $key
 * @property string $label
 * @property ProgramIntakeFieldType $field_type
 * @property bool $is_required
 * @property int $position
 * @property Carbon|null $retired_at
 */
#[Fillable(['organisation_id', 'program_id', 'key', 'label', 'field_type', 'is_required', 'position', 'retired_at'])]
class ProgramIntakeField extends Model
{
    /** @use HasFactory<ProgramIntakeFieldFactory> */
    use BelongsToOrganisation, HasFactory;

    protected static function booted(): void
    {
        static::updating(function (ProgramIntakeField $field): void {
            if ($field->isDirty(['program_id', 'key'])) {
                throw new LogicException('A Program Intake Field stable identity is immutable.');
            }
        });
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'field_type' => ProgramIntakeFieldType::class,
            'is_required' => 'boolean',
            'retired_at' => 'immutable_datetime',
        ];
    }
}
