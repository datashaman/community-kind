<?php

namespace App\Models;

use Database\Factories\AuditDigestManifestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property Carbon $manifest_date
 * @property int $event_count
 * @property string $event_digest
 * @property string|null $previous_manifest_digest
 * @property string $manifest_digest
 * @property string $signature
 * @property string $event_export_path
 * @property string $manifest_path
 * @property Carbon|null $verified_at
 */
#[Fillable(['manifest_date', 'event_count', 'event_digest', 'previous_manifest_digest', 'manifest_digest', 'signature', 'event_export_path', 'manifest_path', 'verified_at'])]
class AuditDigestManifest extends Model
{
    /** @use HasFactory<AuditDigestManifestFactory> */
    use HasFactory, HasUlids;

    protected static function booted(): void
    {
        static::updating(function (AuditDigestManifest $manifest): void {
            if (array_keys($manifest->getDirty()) !== ['verified_at']) {
                throw new LogicException('Audit digest manifests are immutable except for verification timestamps.');
            }
        });
        static::deleting(fn () => throw new LogicException('Audit digest manifests are append-only.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'manifest_date' => 'date',
            'event_count' => 'integer',
            'verified_at' => 'datetime',
        ];
    }
}
