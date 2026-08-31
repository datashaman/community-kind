<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Data\Auditing\VersionedAuditPayload;
use App\Enums\TenantAuditEventType;
use Database\Factories\TenantAuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property TenantAuditEventType $type
 * @property int $schema_version
 * @property array<string, mixed> $payload
 */
#[Fillable(['organisation_id', 'actor_user_id', 'type', 'schema_version', 'subject_type', 'subject_id', 'payload', 'occurred_at'])]
class TenantAuditEvent extends Model
{
    /** @use HasFactory<TenantAuditEventFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (TenantAuditEvent $event): void {
            if ($event->schema_version !== VersionedAuditPayload::CURRENT_VERSION) {
                throw new LogicException('Unsupported tenant audit schema version.');
            }

            VersionedAuditPayload::validate($event->payload, $event->type->payloadSchema());
        });
        static::updating(fn () => throw new LogicException('Tenant audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Tenant audit events are append-only.'));
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
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
            'type' => TenantAuditEventType::class,
            'schema_version' => 'integer',
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
