<?php

namespace App\Policies;

use App\Enums\BillingAccountRole;
use App\Models\BillingAccount;
use App\Models\BillingAccountMembership;
use App\Models\User;

class BillingAccountPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BillingAccount $billingAccount): bool
    {
        return $this->membership($user, $billingAccount) !== null;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BillingAccount $billingAccount): bool
    {
        return $this->membership($user, $billingAccount)?->role === BillingAccountRole::Administrator;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BillingAccount $billingAccount): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BillingAccount $billingAccount): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BillingAccount $billingAccount): bool
    {
        return false;
    }

    public function close(User $user, BillingAccount $billingAccount): bool
    {
        return $this->membership($user, $billingAccount)?->is_owner === true;
    }

    private function membership(User $user, BillingAccount $billingAccount): ?BillingAccountMembership
    {
        return BillingAccountMembership::query()->where('billing_account_id', $billingAccount->id)->where('user_id', $user->id)->whereNull('ended_at')->first();
    }
}
