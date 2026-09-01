<?php

namespace App\Actions\Demo;

use App\Data\Demo\ScenarioCatalog;
use App\Enums\SandboxPairStatus;
use App\Models\Organisation;
use App\Models\SandboxPair;
use Illuminate\Support\Facades\DB;
use Throwable;

/** @phpstan-import-type ScenarioDefinition from ScenarioCatalog */
final class ProvisionSandboxPair
{
    public function __construct(
        private readonly BuildSandboxScenario $buildSandboxScenario,
        private readonly BuildOrganisationScenario $buildOrganisationScenario,
        private readonly CreateSandboxBootstrapToken $createBootstrapToken,
    ) {}

    /** @return array{pair: SandboxPair, token: string} */
    public function handle(int $lifetimeHours = 24): array
    {
        abort_unless(config('demo_sandbox.enabled'), 404);
        $lifetimeHours = max(1, min($lifetimeHours, (int) config('demo_sandbox.maximum_lifetime_hours')));
        $pair = SandboxPair::query()->create([
            'status' => SandboxPairStatus::Provisioning,
            'generation' => 1,
            'expires_at' => now()->addHours($lifetimeHours),
        ]);

        try {
            foreach (ScenarioCatalog::organisations() as $template) {
                $this->buildOrganisationScenario->handle(
                    $this->buildSandboxScenario->handle($template, $pair->id, $pair->generation),
                );
            }

            $this->reconcile($pair);
            $pair->update(['status' => SandboxPairStatus::Ready]);

            return ['pair' => $pair, 'token' => $this->createBootstrapToken->handle($pair)];
        } catch (Throwable $exception) {
            $pair->update(['status' => SandboxPairStatus::Failed, 'failed_at' => now()]);

            throw $exception;
        }
    }

    public function reconcile(SandboxPair $pair): void
    {
        $organisations = $pair->organisations()->get();

        if ($organisations->count() !== 2
            || $organisations->pluck('sandbox_template')->sort()->values()->all() !== ['harbourkind', 'neighbourlink']) {
            throw new \LogicException('The sandbox pair did not reconcile to its pinned scenario.');
        }

        foreach (ScenarioCatalog::organisations() as $template) {
            $organisation = $organisations->firstWhere('sandbox_template', $template['slug']);

            if ($organisation === null) {
                throw new \LogicException('The sandbox pair is missing a pinned Organisation.');
            }

            $this->reconcileOrganisation($organisation, $template);
        }

        if (! DB::table('sandbox_pairs')->where('id', $pair->id)->where('generation', $pair->generation)->exists()) {
            throw new \LogicException('The sandbox generation changed while it was being reconciled.');
        }
    }

    /** @param ScenarioDefinition $template */
    public function reconcileOrganisation(Organisation $organisation, array $template): void
    {
        $expectedCounts = [
            'programs' => count($template['programs']),
            'organisation_members' => count($template['members']),
            'parties' => array_sum($template['party_population']),
            'role_assignments' => collect($template['members'])->whereNotNull('role')->count(),
        ];
        $showcaseCounts = $template['slug'] === 'harbourkind'
            ? [
                'service_cases' => 1,
                'metric_events' => 5,
                'fundraising_campaigns' => 1,
                'donation_funds' => 1,
                'donations' => 1,
                'donation_payments' => 1,
                'donation_receipts' => 1,
                'audience_segments' => 1,
                'supporter_journeys' => 1,
                'supporter_journey_recipients' => 1,
                'supporter_journey_events' => 3,
                'party_consents' => 2,
            ]
            : [
                'service_cases' => 0,
                'metric_events' => 0,
                'fundraising_campaigns' => 0,
                'donation_funds' => 0,
                'donations' => 0,
                'donation_payments' => 0,
                'donation_receipts' => 0,
                'audience_segments' => 0,
                'supporter_journeys' => 0,
                'supporter_journey_recipients' => 0,
                'supporter_journey_events' => 0,
                'party_consents' => 0,
            ];

        foreach ($expectedCounts + $showcaseCounts as $table => $expectedCount) {
            $query = DB::table($table)->where('organisation_id', $organisation->id);

            if (in_array($table, ['programs', 'parties'], true)) {
                $query->whereNull('deleted_at');
            }

            if ($table === 'organisation_members' || $table === 'role_assignments') {
                $query->whereNull('ended_at');
            }

            if ($query->count() !== $expectedCount) {
                throw new \LogicException("The {$organisation->sandbox_template} sandbox did not reconcile {$table} exactly.");
            }
        }
    }
}
