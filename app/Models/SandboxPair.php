<?php

namespace App\Models;

use App\Enums\SandboxPairStatus;
use Database\Factories\SandboxPairFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property SandboxPairStatus $status
 * @property int $generation
 * @property Carbon|null $activated_at
 * @property Carbon $expires_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $purged_at
 * @property-read Collection<int, Organisation> $organisations
 */
#[Fillable(['status', 'generation', 'activated_at', 'expires_at', 'failed_at', 'purged_at'])]
class SandboxPair extends Model
{
    /** @use HasFactory<SandboxPairFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @return HasMany<Organisation, $this> */
    public function organisations(): HasMany
    {
        return $this->hasMany(Organisation::class);
    }

    /** @return HasMany<SandboxBootstrapToken, $this> */
    public function bootstrapTokens(): HasMany
    {
        return $this->hasMany(SandboxBootstrapToken::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SandboxPairStatus::class,
            'generation' => 'integer',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'failed_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }
}
