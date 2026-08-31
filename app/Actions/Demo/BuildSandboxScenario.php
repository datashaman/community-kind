<?php

namespace App\Actions\Demo;

use Ramsey\Uuid\Uuid;

/** @phpstan-import-type ScenarioDefinition from BuildOrganisationScenario */
final class BuildSandboxScenario
{
    /**
     * @param  ScenarioDefinition  $template
     * @return ScenarioDefinition
     */
    public function handle(array $template, string $pairId, int $generation): array
    {
        $shortPairId = substr(str_replace('-', '', $pairId), 0, 8);
        $suffix = "{$shortPairId}-g{$generation}";
        $scenario = $template;
        $scenario['uuid'] = $this->uuid($pairId, $generation, $template['uuid']);
        $scenario['slug'] = "{$template['slug']}-{$suffix}";
        $scenario['name'] = "{$template['name']} Sandbox {$shortPairId}";
        $scenario['template_slug'] = $template['slug'];
        $scenario['sandbox_pair_id'] = $pairId;
        $scenario['demo_generation'] = $generation;

        $scenario['members'] = array_map(function (array $member) use ($pairId, $generation, $suffix): array {
            $member['party_uuid'] = $this->uuid($pairId, $generation, $member['party_uuid']);
            $member['email'] = $this->email($member['email'], $suffix);

            return $member;
        }, $template['members']);

        $scenario['parties'] = array_map(function (array $party) use ($pairId, $generation, $suffix): array {
            $party['uuid'] = $this->uuid($pairId, $generation, $party['uuid']);

            if (isset($party['email'])) {
                $party['email'] = $this->email($party['email'], $suffix);
            }

            return $party;
        }, $template['parties']);

        return $scenario;
    }

    private function uuid(string $pairId, int $generation, string $templateUuid): string
    {
        return Uuid::uuid5($pairId, "generation:{$generation}:{$templateUuid}")->toString();
    }

    private function email(string $email, string $suffix): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return "{$local}+{$suffix}@{$domain}";
    }
}
