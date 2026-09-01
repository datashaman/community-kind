<?php

namespace App\Data\Demo;

use App\Enums\OrganisationRole;
use App\Enums\PartyBusinessRole;
use App\Enums\PartyKind;
use InvalidArgumentException;

/**
 * @phpstan-type ProgramDefinition array{name: string, slug: string, configuration: array{labels: array{request: string, case: string}, stages: list<array{key: string, label: string}>, outcome_measures: list<array{key: string, label: string, unit?: string|null}>, taxonomies: list<array{key: string, label: string, values: list<string>}>, intake_fields: list<array{key: string, label: string, type: string, required: bool}>, eligibility_fields: list<array{key: string, label: string, required?: bool}>, risk_flags: list<array{key: string, label: string}>}}
 * @phpstan-type MemberDefinition array{party_uuid: string, name: string, email: string, telephone: string, owner: bool, role: OrganisationRole|null, program_slugs: list<string>}
 * @phpstan-type PartyDefinition array{uuid: string, kind: PartyKind, name: string, email?: string, telephone?: string, program_slugs?: list<string>, roles?: list<PartyBusinessRole>, interests?: list<string>}
 * @phpstan-type ScenarioDefinition array{uuid: string, name: string, slug: string, timezone: string, currency: string, reporting_at: string, synthetic: true, template_slug?: string, sandbox_pair_id?: string, demo_generation?: int, party_population: array<string, int>, programs: list<ProgramDefinition>, members: list<MemberDefinition>, parties: list<PartyDefinition>}
 */
final class ScenarioCatalog
{
    public const VERSION = '2026.4';

    public const AS_OF = '2026-06-30 23:59:59';

    public const PASSWORD = 'password';

    /** @return array<string, array{uuid: string, organisation_slug: string, synthetic: true}> */
    public static function showcases(): array
    {
        return [
            'request-to-outcome' => self::showcase('30000000-0000-4000-8000-000000000001', 'harbourkind'),
            'donor-to-retained-supporter' => self::showcase('30000000-0000-4000-8000-000000000002', 'harbourkind'),
            'contribution-to-impact' => self::showcase('30000000-0000-4000-8000-000000000003', 'harbourkind'),
            'privacy-and-tenant-boundary' => self::showcase('30000000-0000-4000-8000-000000000004', 'neighbourlink'),
        ];
    }

    /** @return list<ScenarioDefinition> */
    public static function organisations(): array
    {
        $organisations = [
            [
                'uuid' => '10000000-0000-4000-8000-000000000001',
                'name' => 'HarbourKind',
                'slug' => 'harbourkind',
                'timezone' => 'Africa/Johannesburg',
                'currency' => 'ZAR',
                'reporting_at' => '2026-06-30T23:59:59+02:00',
                'synthetic' => true,
                'party_population' => [
                    PartyKind::Person->value => 1750,
                    PartyKind::Organisation->value => 150,
                    PartyKind::Household->value => 100,
                ],
                'programs' => [
                    self::program('Community Drop-in and Material Relief', 'community-drop-in', 'Request', 'Support case', ['received' => 'Received', 'assisted' => 'Assisted']),
                    self::program('Housing and Homelessness Support', 'housing-support', 'Housing request', 'Housing journey', ['referred' => 'Referred', 'housed' => 'Housed']),
                    self::program('Newcomer Settlement', 'newcomer-settlement', 'Settlement request', 'Settlement journey', ['intake' => 'Intake', 'settled' => 'Settled']),
                ],
                'members' => [
                    self::member('11000000-0000-4000-8000-000000000001', 'HarbourKind Demo Administrator', 'admin@harbourkind.example.test', '+1 202-555-0101', true, OrganisationRole::OrganisationAdministrator),
                    self::member('11000000-0000-4000-8000-000000000002', 'HarbourKind Demo Programme Manager', 'manager@harbourkind.example.test', '+1 202-555-0102', false, OrganisationRole::ProgramManager, ['housing-support']),
                    self::member('11000000-0000-4000-8000-000000000003', 'HarbourKind Demo Case Worker', 'caseworker@harbourkind.example.test', '+1 202-555-0103', false, OrganisationRole::CaseWorker, ['housing-support', 'newcomer-settlement']),
                    self::member('11000000-0000-4000-8000-000000000004', 'HarbourKind Demo Engagement Officer', 'engagement@harbourkind.example.test', '+1 202-555-0104', false, OrganisationRole::EngagementOfficer, ['community-drop-in']),
                    self::member('11000000-0000-4000-8000-000000000005', 'CommunityKind Demo Organisation Switcher', 'switcher@community-kind.example.test', '+1 202-555-0105', false, OrganisationRole::ExecutiveViewer),
                    self::member('11000000-0000-4000-8000-000000000006', 'HarbourKind Demo Governance Owner', 'owner@harbourkind.example.test', '+1 202-555-0106', true),
                    self::member('11000000-0000-4000-8000-000000000007', 'HarbourKind Demo Executive Viewer', 'executive@harbourkind.example.test', '+1 202-555-0107', false, OrganisationRole::ExecutiveViewer),
                    self::member('11000000-0000-4000-8000-000000000008', 'HarbourKind Demo Outreach Worker', 'outreach@harbourkind.example.test', '+1 202-555-0108', false, OrganisationRole::CaseWorker, ['community-drop-in']),
                    self::member('11000000-0000-4000-8000-000000000009', 'HarbourKind Demo Settlement Manager', 'settlement.manager@harbourkind.example.test', '+1 202-555-0109', false, OrganisationRole::ProgramManager, ['newcomer-settlement']),
                ],
                'parties' => [
                    self::party('12000000-0000-4000-8000-000000000001', PartyKind::Person, 'Synthetic Client Amina Example', 'amina.client@harbourkind.example.test', '+1 202-555-0111', ['housing-support'], [PartyBusinessRole::Client, PartyBusinessRole::Volunteer], ['Housing', 'Employment']),
                    self::party('12000000-0000-4000-8000-000000000002', PartyKind::Person, 'Synthetic Donor Rowan Example', 'rowan.donor@harbourkind.example.test', '+1 202-555-0112', ['community-drop-in'], [PartyBusinessRole::Donor]),
                    self::party('12000000-0000-4000-8000-000000000003', PartyKind::Household, 'Synthetic Hart Household', programSlugs: ['housing-support'], roles: [PartyBusinessRole::Client], interests: ['Housing']),
                    self::party('12000000-0000-4000-8000-000000000004', PartyKind::Organisation, 'Synthetic Brightwell Community Fund', 'contact@brightwell.example.test', '+1 202-555-0113', ['community-drop-in'], [PartyBusinessRole::Donor, PartyBusinessRole::PartnerContact]),
                ],
            ],
            [
                'uuid' => '20000000-0000-4000-8000-000000000001',
                'name' => 'NeighbourLink',
                'slug' => 'neighbourlink',
                'timezone' => 'Europe/London',
                'currency' => 'GBP',
                'reporting_at' => '2026-06-30T23:59:59+01:00',
                'synthetic' => true,
                'party_population' => [
                    PartyKind::Person->value => 30,
                ],
                'programs' => [
                    self::program('Neighbour Support Network', 'neighbour-support', 'Neighbour request', 'Support case', ['received' => 'Received', 'connected' => 'Connected']),
                ],
                'members' => [
                    self::member('21000000-0000-4000-8000-000000000001', 'NeighbourLink Demo Administrator', 'admin@neighbourlink.example.test', '+1 202-555-0121', true, OrganisationRole::OrganisationAdministrator),
                    self::member('21000000-0000-4000-8000-000000000002', 'CommunityKind Demo Organisation Switcher', 'switcher@community-kind.example.test', '+1 202-555-0105', false, OrganisationRole::ExecutiveViewer),
                    self::member('21000000-0000-4000-8000-000000000003', 'NeighbourLink Demo Governance Owner', 'owner@neighbourlink.example.test', '+1 202-555-0122', true),
                ],
                'parties' => [
                    self::party('22000000-0000-4000-8000-000000000001', PartyKind::Person, 'Synthetic Resident Noor Example', 'noor.resident@neighbourlink.example.test', '+1 202-555-0123', ['neighbour-support'], [PartyBusinessRole::Client], ['Neighbour connection']),
                ],
            ],
        ];

        self::assertSafe($organisations);

        return $organisations;
    }

    /** @param list<array<string, mixed>> $organisations */
    private static function assertSafe(array $organisations): void
    {
        foreach ($organisations as $organisation) {
            if (($organisation['synthetic'] ?? false) !== true) {
                throw new InvalidArgumentException('Every demo organisation must be marked synthetic.');
            }

            foreach ([...$organisation['members'], ...$organisation['parties']] as $identity) {
                if (isset($identity['email']) && ! str_ends_with($identity['email'], '.example.test')) {
                    throw new InvalidArgumentException('Demo email addresses must use the reserved .example.test suffix.');
                }

                if (isset($identity['telephone']) && ! str_starts_with($identity['telephone'], '+1 202-555-01')) {
                    throw new InvalidArgumentException('Demo telephone numbers must use the fictional 202-555-01xx range.');
                }
            }
        }
    }

    /**
     * @param  list<string>  $programSlugs
     * @return array{party_uuid: string, name: string, email: string, telephone: string, owner: bool, role: OrganisationRole|null, program_slugs: list<string>}
     */
    private static function member(string $partyUuid, string $name, string $email, string $telephone, bool $owner, ?OrganisationRole $role = null, array $programSlugs = []): array
    {
        return compact('name', 'email', 'telephone', 'owner', 'role') + [
            'party_uuid' => $partyUuid,
            'program_slugs' => $programSlugs,
        ];
    }

    /**
     * @param  list<string>  $programSlugs
     * @param  list<PartyBusinessRole>  $roles
     * @param  list<string>  $interests
     * @return array{uuid: string, kind: PartyKind, name: string, email?: string, telephone?: string, program_slugs?: list<string>, roles?: list<PartyBusinessRole>, interests?: list<string>}
     */
    private static function party(string $uuid, PartyKind $kind, string $name, ?string $email = null, ?string $telephone = null, array $programSlugs = [], array $roles = [], array $interests = []): array
    {
        return array_filter(compact('uuid', 'kind', 'name', 'email', 'telephone') + [
            'program_slugs' => $programSlugs,
            'roles' => $roles,
            'interests' => $interests,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, string>  $stages
     * @return array{name: string, slug: string, configuration: array{labels: array{request: string, case: string}, stages: list<array{key: string, label: string}>, outcome_measures: list<array{key: string, label: string, unit: string}>, taxonomies: list<array{key: string, label: string, values: list<string>}>, intake_fields: list<array{key: string, label: string, type: string, required: bool}>, eligibility_fields: list<array{key: string, label: string}>, risk_flags: list<array{key: string, label: string}>}}
     */
    private static function program(string $name, string $slug, string $requestLabel, string $caseLabel, array $stages): array
    {
        return [
            'name' => $name,
            'slug' => $slug,
            'configuration' => [
                'labels' => ['request' => $requestLabel, 'case' => $caseLabel],
                'stages' => array_values(collect($stages)->map(fn (string $label, string $key): array => compact('key', 'label'))->all()),
                'outcome_measures' => [['key' => 'progress', 'label' => 'Progress', 'unit' => 'score']],
                'taxonomies' => [['key' => 'need', 'label' => 'Presenting need', 'values' => ['Housing', 'Food', 'Employment', 'Connection']]],
                'intake_fields' => [
                    ['key' => 'preferred_contact_time', 'label' => 'Preferred contact time', 'type' => 'text', 'required' => false],
                    ['key' => 'current_situation', 'label' => 'Current situation', 'type' => 'textarea', 'required' => true],
                ],
                'eligibility_fields' => [
                    ['key' => 'service_area', 'label' => 'Lives in the service area'],
                    ['key' => 'program_fit', 'label' => 'Request fits the Program remit'],
                ],
                'risk_flags' => [
                    ['key' => 'immediate_safety', 'label' => 'Immediate safety concern'],
                    ['key' => 'housing_loss', 'label' => 'At risk of losing housing'],
                ],
            ],
        ];
    }

    /** @return array{uuid: string, organisation_slug: string, synthetic: true} */
    private static function showcase(string $uuid, string $organisationSlug): array
    {
        return ['uuid' => $uuid, 'organisation_slug' => $organisationSlug, 'synthetic' => true];
    }
}
