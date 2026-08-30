<?php

namespace App\Rules;

use App\Models\TeamInvitation;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidTeamInvitation implements ValidationRule
{
    public function __construct(protected ?User $user)
    {
        //
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof TeamInvitation || ! $this->user instanceof User) {
            $fail(__('This invitation was sent to a different email address.'));

            return;
        }

        if (! $value->isPending() || ! $value->isFor($this->user)) {
            $fail(__('This invitation is invalid or unavailable.'));
        }
    }
}
