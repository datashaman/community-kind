<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\OrganisationContext;
use Illuminate\Database\Seeder;

class MembershipHoldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organisation::query()->each(function (Organisation $organisation): void {
            app(OrganisationContext::class)->run($organisation, function () use ($organisation): void {
                $issuer = $organisation->memberships()->where('is_owner', true)->first();
                $membership = $organisation->memberships()->where('is_owner', false)->first();

                if ($issuer === null || $membership === null || $membership->holds()->exists()) {
                    return;
                }

                $membership->holds()->create([
                    'organisation_id' => $organisation->id,
                    'reason' => 'Example completed membership review',
                    'starts_at' => now()->subMonth(),
                    'review_at' => now()->subWeeks(3),
                    'expires_at' => now()->subWeeks(2),
                    'released_at' => now()->subWeeks(2),
                    'issued_by' => $issuer->user_id,
                    'released_by' => $issuer->user_id,
                ]);
            });
        });
    }
}
