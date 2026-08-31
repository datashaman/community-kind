<?php

namespace App\Policies;

use App\Authorization\CaseAccess;
use App\Models\RestrictedAccessGrant;
use App\Models\User;

class RestrictedAccessGrantPolicy
{
    public function __construct(private readonly CaseAccess $access) {}

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RestrictedAccessGrant $restrictedAccessGrant): bool
    {
        return $restrictedAccessGrant->serviceCase !== null
            && $this->access->canManageAccess($user, $restrictedAccessGrant->serviceCase);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RestrictedAccessGrant $restrictedAccessGrant): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RestrictedAccessGrant $restrictedAccessGrant): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RestrictedAccessGrant $restrictedAccessGrant): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RestrictedAccessGrant $restrictedAccessGrant): bool
    {
        return false;
    }
}
