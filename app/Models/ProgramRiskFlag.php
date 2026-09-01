<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\ProgramRiskFlagFactory;
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
 */
#[Fillable(['organisation_id', 'program_id', 'key', 'label', 'position', 'retired_at'])]
class ProgramRiskFlag extends Model
{
    /** @use HasFactory<ProgramRiskFlagFactory> */
    use BelongsToOrganisation, HasFactory;

    protected static function booted(): void
    {
        static::updating(function (ProgramRiskFlag $flag): void {
            if ($flag->isDirty(['program_id', 'key'])) {
                throw new LogicException('A Program Risk Flag stable identity is immutable.');
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
