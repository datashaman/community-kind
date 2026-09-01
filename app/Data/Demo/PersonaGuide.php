<?php

namespace App\Data\Demo;

use App\Enums\OrganisationRole;
use App\Models\Organisation;

class PersonaGuide
{
    /**
     * @return array{
     *     responsibility: string,
     *     boundary: string,
     *     tasks: list<array{label: string, description: string, href: string}>
     * }
     */
    public static function for(OrganisationRole $role, Organisation $organisation): array
    {
        return match ($role) {
            OrganisationRole::OrganisationAdministrator => [
                'responsibility' => 'Keep the organisation, programmes, and staff access safely configured.',
                'boundary' => 'Can inspect organisation-wide setup and audit history. Demo confinement still blocks invitations, uploads, domains, payments, and outbound messages.',
                'tasks' => [
                    self::task('Review programme setup', 'See how service programmes are structured.', 'programs.index', $organisation),
                    self::task('Inspect reporting publication', 'Review which registered metrics may leave the organisation.', 'reporting-publication.index', $organisation),
                    self::task('Trace the audit history', 'See how important actions remain accountable.', 'audit.index', $organisation),
                ],
            ],
            OrganisationRole::ProgramManager => [
                'responsibility' => 'Coordinate demand, assignments, and delivery across assigned programmes.',
                'boundary' => 'Can inspect service records within programme scope, but cannot administer the organisation or see work outside that scope.',
                'tasks' => [
                    self::task('Review the intake queue', 'Follow requests from triage into service delivery.', 'intakes.index', $organisation),
                    self::task('Explore participant profiles', 'See consent-aware context across programme work.', 'parties.index', $organisation),
                    self::task('Check operational accountability', 'Review the audit trail for service decisions.', 'audit.index', $organisation),
                ],
            ],
            OrganisationRole::CaseWorker => [
                'responsibility' => 'Support people through assigned cases while protecting sensitive information.',
                'boundary' => 'Can reach assigned programme and case records only; management, supporter, and organisation controls stay out of scope.',
                'tasks' => [
                    self::task('Open assigned service requests', 'See the hand-off from intake to active support.', 'intakes.index', $organisation),
                    self::task('Review participant context', 'Inspect the profiles available to this worker.', 'parties.index', $organisation),
                    self::task('Follow accountable actions', 'See the service activity recorded for review.', 'audit.index', $organisation),
                ],
            ],
            OrganisationRole::EngagementOfficer => [
                'responsibility' => 'Build community support through donors, volunteers, audiences, and journeys.',
                'boundary' => 'Can work with supporter-safe information, but cannot open confidential service-delivery records.',
                'tasks' => [
                    self::task('Follow simulated donations', 'Review supporter gifts and stewardship context.', 'donations.index', $organisation),
                    self::task('Explore volunteer opportunities', 'See recruitment and follow-up in one place.', 'volunteers.index', $organisation),
                    self::task('Inspect welcome journeys', 'Review how audiences move through engagement.', 'supporter-journeys.index', $organisation),
                ],
            ],
            OrganisationRole::ExecutiveViewer => [
                'responsibility' => 'Understand performance, outcomes, and organisational impact without entering frontline records.',
                'boundary' => 'Can inspect aggregate impact views; operational case, supporter, and configuration records remain unavailable.',
                'tasks' => [
                    self::task('Read the impact dashboard', 'Start with an organisation-wide view of progress.', 'dashboard', $organisation),
                    self::task('Review published impact packs', 'See how evidence is prepared for leadership and partners.', 'impact-snapshots.index', $organisation),
                ],
            ],
        };
    }

    /** @return array{label: string, description: string, href: string} */
    private static function task(string $label, string $description, string $routeName, Organisation $organisation): array
    {
        return [
            'label' => $label,
            'description' => $description,
            'href' => route($routeName, ['current_organisation' => $organisation]),
        ];
    }
}
