<?php

namespace App\Actions\Organisations;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganisation
{
    /**
     * Create a new organisation and add the user as owner.
     */
    public function handle(User $user, string $name): Organisation
    {
        return DB::transaction(function () use ($user, $name) {
            $organisation = Organisation::create([
                'name' => $name,
            ]);

            $membership = $organisation->memberships()->create([
                'user_id' => $user->id,
                'is_owner' => true,
                'role' => null,
            ]);

            $user->switchOrganisation($organisation);

            return $organisation;
        });
    }
}
