<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\OrganisationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'invitation' => ['required', 'string'],
        ])->validate();

        return DB::transaction(function () use ($input) {
            $invitation = OrganisationInvitation::query()
                ->where('token_hash', hash('sha256', $input['invitation']))
                ->lockForUpdate()
                ->first();

            if (! $invitation?->isPending() || $invitation->email !== Str::lower($input['email'])) {
                throw ValidationException::withMessages([
                    'invitation' => __('This invitation is invalid or unavailable.'),
                ]);
            }

            return User::create([
                'name' => $input['name'],
                'email' => Str::lower($input['email']),
                'password' => $input['password'],
            ]);
        });
    }
}
