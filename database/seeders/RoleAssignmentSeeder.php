<?php

namespace Database\Seeders;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\OrganisationContext;
use Illuminate\Database\Seeder;

class RoleAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organisation::query()->each(function (Organisation $organisation): void {
            app(OrganisationContext::class)->run($organisation, function () use ($organisation): void {
                $organisation->memberships()
                    ->where('is_owner', false)
                    ->get()
                    ->each(function ($membership) use ($organisation): void {
                        if ($membership->roleAssignments()->whereNull('ended_at')->doesntExist()) {
                            $membership->roleAssignments()->create([
                                'organisation_id' => $organisation->id,
                                'role' => OrganisationRole::CaseWorker,
                            ]);
                        }
                    });
            });
        });
    }
}
