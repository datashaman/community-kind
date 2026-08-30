<?php

namespace App\Models;

use App\Concerns\HasOrganisations;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $recovery_codes_acknowledged_at
 * @property string|null $remember_token
 * @property int|null $current_organisation_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation|null $currentOrganisation
 * @property-read Collection<int, Organisation> $ownedOrganisations
 * @property-read Collection<int, Membership> $organisationMemberships
 * @property-read Collection<int, Organisation> $organisations
 */
#[Fillable(['name', 'email', 'password', 'current_organisation_id', 'recovery_codes_acknowledged_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasOrganisations, Notifiable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->exists && $user->isDirty(['two_factor_secret', 'two_factor_recovery_codes'])) {
                $user->recovery_codes_acknowledged_at = null;
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'recovery_codes_acknowledged_at' => 'datetime',
        ];
    }

    public function hasAcknowledgedRecoveryCodes(): bool
    {
        return $this->recovery_codes_acknowledged_at !== null;
    }
}
