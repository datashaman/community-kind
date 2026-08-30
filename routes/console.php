<?php

use App\Models\OrganisationInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    OrganisationInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired organisation invitations');
