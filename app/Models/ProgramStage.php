<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\ProgramStageFactory;
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
 * @property int $position
 * @property Carbon|null $retired_at
 * @property-read Program $program
 */
#[Fillable(['organisation_id', 'program_id', 'key', 'label', 'position', 'retired_at'])]
class ProgramStage extends Model
{
    /** @use HasFactory<ProgramStageFactory> */
    use BelongsToOrganisation, HasFactory;

    protected static function booted(): void
    {
        static::updating(function (ProgramStage $stage): void {
            if ($stage->isDirty(['program_id', 'key'])) {
                throw new LogicException('A Program Stage stable identity is immutable.');
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
        return ['retired_at' => 'immutable_datetime'];
    }
}
