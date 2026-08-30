<?php

namespace Database\Seeders;

use App\Enums\PartyKind;
use App\Models\Organisation;
use App\Models\Party;
use App\OrganisationContext;
use Illuminate\Database\Seeder;

class PartySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organisation::query()->each(function (Organisation $organisation): void {
            app(OrganisationContext::class)->run(
                $organisation,
                fn () => Party::factory()->count(5)->create([
                    'organisation_id' => $organisation->id,
                    'kind' => PartyKind::Person,
                ]),
            );
        });
    }
}
