<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\ProgramEligibilityQuestionFactory;
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
 * @property bool $is_required
 * @property int $position
 * @property Carbon|null $retired_at
 */
#[Fillable(['organisation_id', 'program_id', 'key', 'label', 'is_required', 'position', 'retired_at'])]
class ProgramEligibilityQuestion extends Model
{
    /** @use HasFactory<ProgramEligibilityQuestionFactory> */
    use BelongsToOrganisation, HasFactory;

    protected static function booted(): void
    {
        static::updating(function (ProgramEligibilityQuestion $question): void {
            if ($question->isDirty(['program_id', 'key'])) {
                throw new LogicException('A Program Eligibility Question stable identity is immutable.');
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
        return ['is_required' => 'boolean', 'retired_at' => 'immutable_datetime'];
    }
}
