<?php

namespace App\Actions\Demo;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Data\Demo\ScenarioCatalog;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationStatus;
use App\Enums\TenantAuditEventType;
use App\Models\Organisation;
use App\Models\SandboxPair;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/** @phpstan-import-type ScenarioDefinition from ScenarioCatalog */
final class ResetSandboxOrganisation
{
    public function __construct(
        private readonly BuildSandboxScenario $buildSandboxScenario,
        private readonly BuildOrganisationScenario $buildOrganisationScenario,
        private readonly ProvisionSandboxPair $provisionSandboxPair,
        private readonly CreateSandboxBootstrapToken $createBootstrapToken,
        private readonly RecordTenantAuditEvent $recordAudit,
        private readonly OrganisationContext $organisationContext,
    ) {}

    /** @return array{organisation: Organisation, token: string} */
    public function handle(Organisation $organisation, User $actor): array
    {
        abort_unless(config('demo_sandbox.enabled'), 404);
        $pairId = $organisation->sandbox_pair_id;

        return DB::transaction(function () use ($organisation, $actor, $pairId): array {
            $pair = SandboxPair::query()->lockForUpdate()->findOrFail($pairId);
            $organisation = Organisation::query()->lockForUpdate()->findOrFail($organisation->id);
            $isAdministrator = DB::table('organisation_members')
                ->where('organisation_id', $organisation->id)
                ->where('user_id', $actor->id)
                ->whereNull('ended_at')
                ->where(function ($query) use ($organisation): void {
                    $query->where('is_owner', true)
                        ->orWhereExists(fn ($roles) => $roles
                            ->selectRaw('1')
                            ->from('role_assignments')
                            ->whereColumn('role_assignments.membership_id', 'organisation_members.id')
                            ->where('role_assignments.organisation_id', $organisation->id)
                            ->where('role_assignments.role', OrganisationRole::OrganisationAdministrator)
                            ->whereNull('role_assignments.program_id')
                            ->whereNull('role_assignments.ended_at'));
                })
                ->exists();

            abort_unless($isAdministrator, 403);

            if ($organisation->sandbox_pair_id !== $pair->id || ! $organisation->is_synthetic || $organisation->sandbox_template === null || ! $pair->status->isAccessible()) {
                throw new LogicException('Only an accessible synthetic sandbox Organisation can be reset.');
            }

            $template = $this->template($organisation->sandbox_template);

            $oldOrganisationId = $organisation->id;
            $oldSlug = $organisation->slug;
            $nextGeneration = $organisation->demo_generation + 1;
            $userIds = DB::table('organisation_members')->where('organisation_id', $oldOrganisationId)->pluck('user_id');

            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            $organisation->forceFill([
                'status' => OrganisationStatus::Deleted,
                'status_changed_at' => now(),
                'access_version' => $organisation->access_version + 1,
                'signed_links_invalidated_at' => now(),
                'slug' => "retired-sandbox-{$organisation->id}-".Str::lower((string) Str::ulid()),
            ])->save();
            $organisation->delete();

            $scenario = $this->buildSandboxScenario->handle($template, $pair->id, $nextGeneration);
            $scenario['slug'] = $oldSlug;
            $replacement = $this->buildOrganisationScenario->handle($scenario);
            $this->provisionSandboxPair->reconcileOrganisation($replacement, $template);

            $this->organisationContext->run($replacement, fn () => $this->recordAudit->handle(
                $replacement,
                TenantAuditEventType::DemoOrganisationReset,
                'organisation',
                (string) $replacement->id,
                ['generation' => $nextGeneration, 'template' => $replacement->sandbox_template],
                $actor,
            ));

            return [
                'organisation' => $replacement,
                'token' => $this->createBootstrapToken->handle($pair),
            ];
        });
    }

    /** @return ScenarioDefinition */
    private function template(string $slug): array
    {
        foreach (ScenarioCatalog::organisations() as $scenario) {
            if ($scenario['slug'] === $slug) {
                return $scenario;
            }
        }

        throw new LogicException('The sandbox template is no longer available.');
    }
}
