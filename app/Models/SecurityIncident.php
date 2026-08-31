<?php

namespace App\Models;

use App\Enums\SecurityIncidentClassification;
use App\Enums\SecurityIncidentSeverity;
use App\Enums\SecurityIncidentStatus;
use Database\Factories\SecurityIncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property SecurityIncidentClassification $classification
 * @property SecurityIncidentSeverity $severity
 * @property SecurityIncidentStatus $status
 * @property int|null $commander_user_id
 */
#[Fillable(['classification', 'severity', 'status', 'detection_source', 'summary', 'detected_at', 'first_awareness_at', 'confirmed_at', 'closed_at', 'commander_user_id'])]
class SecurityIncident extends Model
{
    /** @use HasFactory<SecurityIncidentFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<User, $this> */
    public function commander(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commander_user_id');
    }

    /** @return HasMany<SecurityIncidentEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(SecurityIncidentEntry::class, 'incident_uuid');
    }

    /** @return HasMany<SecurityIncidentOrganisation, $this> */
    public function organisationImpacts(): HasMany
    {
        return $this->hasMany(SecurityIncidentOrganisation::class, 'incident_uuid');
    }

    /** @return HasMany<InstallationControl, $this> */
    public function installationControls(): HasMany
    {
        return $this->hasMany(InstallationControl::class, 'incident_uuid');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'classification' => SecurityIncidentClassification::class,
            'severity' => SecurityIncidentSeverity::class,
            'status' => SecurityIncidentStatus::class,
            'detected_at' => 'datetime',
            'first_awareness_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
