<?php

namespace App\Models;

use Database\Factories\PlatformSecurityEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $type
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
        'actor_user_id',
        'subject_user_id',
        'metadata',
        'occurred_at',
    ];

    public $timestamps = false;

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
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
