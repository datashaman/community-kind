<?php

use App\Models\OrganisationInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    OrganisationInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired organisation invitations');

Schedule::command('organisations:purge-scheduled')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping(60);

Schedule::command('case-documents:reconcile')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(10);

Schedule::command('audit:digest:create')
    ->dailyAt('00:15')
    ->onOneServer()
    ->withoutOverlapping(60);

Schedule::command('audit:digest:verify')
    ->dailyAt('01:00')
    ->onOneServer()
    ->withoutOverlapping(60);

Schedule::command('demo:sandbox:expire')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(10);

Schedule::command('demo:sandbox:purge')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping(60);
