<?php

namespace App\Data\Demo;

use App\Enums\OrganisationRole;
use App\Enums\PartyKind;
use InvalidArgumentException;

final class ScenarioCatalog
{
    public const VERSION = '2026.1';

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

    /**
     * @return list<array{
     *     uuid: string, name: string, slug: string, timezone: string, currency: string, reporting_at: string, synthetic: true,
     *     party_population: array<string, int>,
     *     programs: list<array{name: string, slug: string}>,
     *     members: list<array{party_uuid: string, name: string, email: string, telephone: string, owner: bool, role: OrganisationRole|null, program_slugs: list<string>}>,
     *     parties: list<array{uuid: string, kind: PartyKind, name: string, email?: string, telephone?: string}>
     * }>
     */
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
                    ['name' => 'Community Drop-in and Material Relief', 'slug' => 'community-drop-in'],
                    ['name' => 'Housing and Homelessness Support', 'slug' => 'housing-support'],
                    ['name' => 'Newcomer Settlement', 'slug' => 'newcomer-settlement'],
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
                    self::party('12000000-0000-4000-8000-000000000001', PartyKind::Person, 'Synthetic Client Amina Example', 'amina.client@harbourkind.example.test', '+1 202-555-0111'),
                    self::party('12000000-0000-4000-8000-000000000002', PartyKind::Person, 'Synthetic Donor Rowan Example', 'rowan.donor@harbourkind.example.test', '+1 202-555-0112'),
                    self::party('12000000-0000-4000-8000-000000000003', PartyKind::Household, 'Synthetic Hart Household'),
                    self::party('12000000-0000-4000-8000-000000000004', PartyKind::Organisation, 'Synthetic Brightwell Community Fund', 'contact@brightwell.example.test', '+1 202-555-0113'),
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
                    ['name' => 'Neighbour Support Network', 'slug' => 'neighbour-support'],
                ],
                'members' => [
                    self::member('21000000-0000-4000-8000-000000000001', 'NeighbourLink Demo Administrator', 'admin@neighbourlink.example.test', '+1 202-555-0121', true, OrganisationRole::OrganisationAdministrator),
                    self::member('21000000-0000-4000-8000-000000000002', 'CommunityKind Demo Organisation Switcher', 'switcher@community-kind.example.test', '+1 202-555-0105', false, OrganisationRole::ExecutiveViewer),
                    self::member('21000000-0000-4000-8000-000000000003', 'NeighbourLink Demo Governance Owner', 'owner@neighbourlink.example.test', '+1 202-555-0122', true),
                ],
                'parties' => [
                    self::party('22000000-0000-4000-8000-000000000001', PartyKind::Person, 'Synthetic Resident Noor Example', 'noor.resident@neighbourlink.example.test', '+1 202-555-0123'),
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

    /** @return array{uuid: string, kind: PartyKind, name: string, email?: string, telephone?: string} */
    private static function party(string $uuid, PartyKind $kind, string $name, ?string $email = null, ?string $telephone = null): array
    {
        return array_filter(compact('uuid', 'kind', 'name', 'email', 'telephone'), fn (mixed $value): bool => $value !== null);
    }

    /** @return array{uuid: string, organisation_slug: string, synthetic: true} */
    private static function showcase(string $uuid, string $organisationSlug): array
    {
        return ['uuid' => $uuid, 'organisation_slug' => $organisationSlug, 'synthetic' => true];
    }
}
