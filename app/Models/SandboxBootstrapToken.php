<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sandbox_pair_id
 * @property string $token_hash
 * @property int $generation
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property Carbon|null $revoked_at
 * @property-read SandboxPair $sandboxPair
 */
#[Fillable(['sandbox_pair_id', 'token_hash', 'generation', 'expires_at', 'used_at', 'revoked_at'])]
class SandboxBootstrapToken extends Model
{
    use HasUlids;

    /** @return BelongsTo<SandboxPair, $this> */
    public function sandboxPair(): BelongsTo
    {
        return $this->belongsTo(SandboxPair::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
