<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrganisationConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property OrganisationConfigurationArea $area
 * @property string $configuration_key
 * @property int $version
 * @property array<string, mixed> $definition
 * @property OrganisationConfigurationStatus $status
 * @property string|null $supersedes_id
 * @property CarbonImmutable|null $activated_at
 * @property-read Organisation $organisation
 */
#[Fillable(['organisation_id', 'area', 'configuration_key', 'version', 'definition', 'status', 'supersedes_id', 'created_by_user_id', 'activated_by_user_id', 'activated_at'])]
class OrganisationConfiguration extends Model
{
    /** @use HasFactory<OrganisationConfigurationFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (OrganisationConfiguration $configuration): void {
            if ($configuration->isDirty(['organisation_id', 'area', 'configuration_key', 'version', 'definition', 'supersedes_id', 'created_by_user_id'])) {
                throw new LogicException('Configuration definitions are immutable; create a new version instead.');
            }
            if ($configuration->isDirty('status')) {
                $from = OrganisationConfigurationStatus::from((string) $configuration->getRawOriginal('status'));
                $allowed = match ($from) {
                    OrganisationConfigurationStatus::Draft => [OrganisationConfigurationStatus::Active, OrganisationConfigurationStatus::Retired],
                    OrganisationConfigurationStatus::Active => [OrganisationConfigurationStatus::Superseded, OrganisationConfigurationStatus::Retired],
                    OrganisationConfigurationStatus::Superseded => [],
                    OrganisationConfigurationStatus::Retired => [],
                };
                if (! in_array($configuration->status, $allowed, true)) {
                    throw new LogicException("Cannot transition configuration from {$from->value} to {$configuration->status->value}.");
                }
            }
        });
        static::deleting(fn () => throw new LogicException('Configuration version history is immutable.'));
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<OrganisationConfiguration, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    protected function casts(): array
    {
        return [
            'area' => OrganisationConfigurationArea::class,
            'version' => 'integer',
            'definition' => 'array',
            'status' => OrganisationConfigurationStatus::class,
            'activated_at' => 'immutable_datetime',
        ];
    }
}
