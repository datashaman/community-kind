<?php

namespace App\Reporting;

use App\Enums\OrganisationRole;

class MetricRegistry
{
    public const VERSION = '2026.4';

    /** @return list<string> */
    public function ids(): array
    {
        return array_column($this->all(), 'id');
    }

    /** @return list<array{id: string, version: string, category: string, domain: string, label: string, description: string, formula: string, unit: string, dimensions: list<string>}> */
    public function all(): array
    {
        $definitions = [];

        foreach (OrganisationRole::cases() as $role) {
            foreach ($this->forRole($role) as $definition) {
                $definitions[$definition['id']] = $definition;
            }
        }

        return array_values($definitions);
    }

    /** @return list<array{id: string, version: string, category: string, domain: string, label: string, description: string, formula: string, unit: string, dimensions: list<string>}> */
    public function forRole(?OrganisationRole $role): array
    {
        $definitions = [
            $this->definition('service.requests_received', 'input', 'service', 'Requests received', 'Submitted requests created in the reporting period.', 'count(requests)', 'count', ['date', 'program', 'area', 'location', 'cohort']),
            $this->definition('service.case_interactions', 'activity', 'service', 'Case interactions', 'Recorded service interactions in the reporting period.', 'count(interactions)', 'count', ['date', 'program', 'area', 'location', 'cohort']),
            $this->definition('service.services_delivered', 'output', 'service', 'Services delivered', 'Net immutable service-delivered metric events in the reporting period.', 'sum(service_delivered events)', 'count', ['date', 'program', 'area', 'location', 'cohort']),
            $this->definition('service.goal_achievement_rate', 'outcome', 'service', 'Goal achievement rate', 'Achieved goals divided by all achieved or not-achieved goal events.', 'achieved / (achieved + not achieved)', 'percent', ['date', 'program', 'area', 'location', 'cohort']),
            $this->definition('fundraising.successful_donations', 'input', 'fundraising', 'Successful donations', 'Payments that entered succeeded state in the reporting period.', 'count(distinct succeeded payments)', 'count', ['date', 'area', 'location', 'cohort', 'campaign']),
            $this->definition('engagement.welcome_deliveries', 'activity', 'engagement', 'Welcome deliveries', 'Recipients with a simulated delivered event in the reporting period.', 'count(distinct delivered recipients)', 'count', ['date', 'area', 'location', 'cohort', 'campaign']),
            $this->definition('engagement.volunteer_applications', 'activity', 'engagement', 'Volunteer applications', 'Volunteer applications submitted in the reporting period.', 'count(applications)', 'count', ['date', 'area', 'location', 'cohort']),
            $this->definition('engagement.volunteer_hours', 'output', 'engagement', 'Volunteer hours', 'Attended volunteer time recorded in the reporting period.', 'sum(minutes) / 60', 'hours', ['date', 'area', 'location', 'cohort']),
            $this->definition('engagement.event_attendance', 'output', 'engagement', 'Event attendance', 'Event registrations entering attended state in the reporting period.', 'count(attended registrations)', 'count', ['date', 'area', 'location', 'cohort']),
            $this->definition('engagement.in_kind_fulfilments', 'output', 'engagement', 'In-kind fulfilments', 'In-kind offers fulfilled in the reporting period.', 'count(fulfilled offers)', 'count', ['date', 'area', 'location', 'cohort']),
            $this->definition('engagement.partner_commitments', 'activity', 'engagement', 'Partner commitments', 'Partner commitments recorded in the reporting period.', 'count(commitments)', 'count', ['date', 'area', 'location', 'cohort']),
            $this->definition('data.missing_service_area_rate', 'quality', 'data', 'Missing service-area rate', 'Party records created in the reporting period without a service-area value.', 'parties missing service area / parties created', 'percent', ['date', 'cohort']),
            $this->definition('data.missing_contact_rate', 'quality', 'data', 'Missing contact rate', 'Party records created in the reporting period without an email or telephone contact.', 'parties missing email and telephone / parties created', 'percent', ['date', 'cohort']),
            $this->definition('fundraising.net_raised', 'output', 'fundraising', 'Net raised', 'Succeeded payment value less refunds recorded in the reporting period.', 'sum(succeeded payment minor units) - sum(refund minor units)', 'currency', ['date', 'area', 'location', 'cohort', 'campaign']),
            $this->definition('engagement.meaningful_action_rate', 'outcome', 'engagement', 'Meaningful action rate', 'Recipients with a meaningful action divided by delivered recipients.', 'meaningful recipients / delivered recipients', 'percent', ['date', 'area', 'location', 'cohort', 'campaign']),
        ];

        $domains = match ($role) {
            OrganisationRole::ProgramManager => ['service'],
            OrganisationRole::EngagementOfficer => ['fundraising', 'engagement'],
            OrganisationRole::ExecutiveViewer => ['service', 'fundraising', 'engagement', 'data'],
            default => [],
        };

        return array_values(array_filter($definitions, fn (array $definition): bool => in_array($definition['domain'], $domains, true)));
    }

    /** @param list<string> $dimensions
     * @return array{id: string, version: string, category: string, domain: string, label: string, description: string, formula: string, unit: string, dimensions: list<string>}
     */
    private function definition(string $id, string $category, string $domain, string $label, string $description, string $formula, string $unit, array $dimensions): array
    {
        return compact('id', 'category', 'domain', 'label', 'description', 'formula', 'unit', 'dimensions') + ['version' => self::VERSION];
    }
}
