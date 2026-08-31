<?php

namespace App\Policies;

use App\Authorization\CaseAccess;
use App\Models\ServiceCase;
use App\Models\User;

class ServiceCasePolicy
{
    public function __construct(private readonly CaseAccess $access) {}

    public function view(User $user, ServiceCase $case): bool
    {
        return $this->access->canView($user, $case);
    }

    public function update(User $user, ServiceCase $case): bool
    {
        return $this->view($user, $case) && ! $case->status->isTerminal();
    }

    public function viewSensitive(User $user, ServiceCase $case): bool
    {
        return $this->access->canViewSensitive($user, $case);
    }

    public function manageAccess(User $user, ServiceCase $case): bool
    {
        return $this->access->canManageAccess($user, $case);
    }

    public function export(User $user, ServiceCase $case): bool
    {
        return $this->access->canExport($user, $case);
    }
}
