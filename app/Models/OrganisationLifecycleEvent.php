<?php

namespace App\Models;

use App\Enums\OrganisationLifecycleEventType;
use App\Enums\OrganisationStatus;
use Database\Factories\OrganisationLifecycleEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organisation_id', 'actor_user_id', 'type', 'from_status', 'to_status', 'metadata', 'occurred_at'])]
class OrganisationLifecycleEvent extends Model
{
    /** @use HasFactory<OrganisationLifecycleEventFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

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
            'type' => OrganisationLifecycleEventType::class,
            'from_status' => OrganisationStatus::class,
            'to_status' => OrganisationStatus::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
