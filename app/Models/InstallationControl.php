<?php

namespace App\Models;

use App\Enums\InstallationCapability;
use Database\Factories\InstallationControlFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property InstallationCapability $capability
 */
#[Fillable(['incident_uuid', 'capability', 'reason_code', 'activated_by_user_id', 'activated_at', 'released_by_user_id', 'released_at', 'release_reason_code'])]
class InstallationControl extends Model
{
    /** @use HasFactory<InstallationControlFactory> */
    use HasFactory, HasUlids;

    public static function isPaused(InstallationCapability $capability): bool
    {
        return static::query()
            ->where('capability', $capability)
            ->whereNull('released_at')
            ->exists();
    }

    /** @return BelongsTo<SecurityIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(SecurityIncident::class, 'incident_uuid');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capability' => InstallationCapability::class,
            'activated_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }
}
