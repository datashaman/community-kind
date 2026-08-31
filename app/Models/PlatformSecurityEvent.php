<?php

namespace App\Models;

use App\Data\Auditing\VersionedAuditPayload;
use App\Enums\PlatformSecurityEventType;
use Database\Factories\PlatformSecurityEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property PlatformSecurityEventType $type
 * @property int|null $actor_user_id
 * @property int|null $subject_user_id
 * @property array<string, mixed> $metadata
 * @property Carbon $occurred_at
 */
class PlatformSecurityEvent extends Model
{
    /** @use HasFactory<PlatformSecurityEventFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'type',
        'schema_version',
        'incident_uuid',
        'actor_user_id',
        'subject_user_id',
        'metadata',
        'occurred_at',
    ];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (PlatformSecurityEvent $event): void {
            if ($event->schema_version !== VersionedAuditPayload::CURRENT_VERSION) {
                throw new LogicException('Unsupported platform security event schema version.');
            }

            VersionedAuditPayload::validate($event->metadata, $event->type->payloadSchema());
        });
        static::updating(fn () => throw new LogicException('Platform security events are append-only.'));
        static::deleting(fn () => throw new LogicException('Platform security events are append-only.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PlatformSecurityEventType::class,
            'schema_version' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
