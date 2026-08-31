<?php

namespace App\Models;

use App\Enums\SecurityIncidentEntryType;
use Database\Factories\SecurityIncidentEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['incident_uuid', 'actor_user_id', 'type', 'summary', 'reference', 'status', 'due_at', 'occurred_at'])]
class SecurityIncidentEntry extends Model
{
    /** @use HasFactory<SecurityIncidentEntryFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Security incident entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Security incident entries are append-only.'));
    }

    /** @return BelongsTo<SecurityIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(SecurityIncident::class, 'incident_uuid');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => SecurityIncidentEntryType::class,
            'due_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }
}
