<?php

namespace Database\Seeders;

use App\Actions\Demo\BuildOrganisationScenario;
use App\Data\Demo\ScenarioCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use LogicException;

class CommunityKindScenarioSeeder extends Seeder
{
    public function run(BuildOrganisationScenario $buildOrganisationScenario): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Synthetic demo scenarios cannot be seeded in production.');
        }

        try {
            foreach (ScenarioCatalog::organisations() as $scenario) {
                Date::setTestNow($scenario['reporting_at']);
                $buildOrganisationScenario->handle($scenario);
            }
        } finally {
            Date::setTestNow();
        }
    }
}
