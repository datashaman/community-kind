<?php

namespace App\Reporting;

use App\Enums\OrganisationRole;

class MetricRegistry
{
    public const VERSION = '2026.2';

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
            $this->definition('fundraising.net_raised', 'output', 'fundraising', 'Net raised', 'Succeeded payment value less refunds recorded in the reporting period.', 'sum(succeeded payment minor units) - sum(refund minor units)', 'currency', ['date', 'area', 'location', 'cohort', 'campaign']),
            $this->definition('engagement.meaningful_action_rate', 'outcome', 'engagement', 'Meaningful action rate', 'Recipients with a meaningful action divided by delivered recipients.', 'meaningful recipients / delivered recipients', 'percent', ['date', 'area', 'location', 'cohort', 'campaign']),
        ];

        $domains = match ($role) {
            OrganisationRole::ProgramManager => ['service'],
            OrganisationRole::EngagementOfficer => ['fundraising', 'engagement'],
            OrganisationRole::ExecutiveViewer => ['service', 'fundraising', 'engagement'],
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
