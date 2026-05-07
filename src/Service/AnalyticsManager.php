<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

class AnalyticsManager
{
    public function __construct(private readonly TypesenseClient $client) {}

    /**
     * @param array<string, array<string, mixed>> $rules
     * @return array<string, mixed>
     */
    public function applyConfiguredRules(array $rules): array
    {
        $results = [];
        foreach ($this->normalizeRules($rules) as $name => $rule) {
            $results[$name] = $this->client->createAnalyticsRules([$rule]);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function listRules(): array
    {
        return $this->client->listAnalyticsRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveRule(string $name): array
    {
        return $this->client->retrieveAnalyticsRule($name);
    }

    /**
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public function updateRule(string $name, array $rule): array
    {
        return $this->client->updateAnalyticsRule($name, $rule);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteRule(string $name): array
    {
        return $this->client->deleteAnalyticsRule($name);
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function createEvent(array $event): array
    {
        return $this->client->createAnalyticsEvent($event);
    }

    /**
     * @param array<string, array<string, mixed>> $rules
     * @return array<string, array<string, mixed>>
     */
    private function normalizeRules(array $rules): array
    {
        foreach ($rules as $name => $rule) {
            if (!isset($rule['name'])) {
                $rules[$name]['name'] = (string) $name;
            }
        }

        return $rules;
    }
}
