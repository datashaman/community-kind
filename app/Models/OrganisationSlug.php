<?php

namespace App\Models;

use Database\Factories\OrganisationSlugFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $slug
 * @property Carbon $redirect_until
 * @property Carbon $quarantined_until
 */
#[Fillable(['organisation_id', 'slug', 'redirect_until', 'quarantined_until'])]
class OrganisationSlug extends Model
{
    /** @use HasFactory<OrganisationSlugFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'redirect_until' => 'datetime',
            'quarantined_until' => 'datetime',
        ];
    }
}
