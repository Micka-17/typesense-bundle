<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

class PresetManager
{
    public function __construct(private readonly TypesenseClient $client) {}

    /**
     * @param array<string, array<string, mixed>> $presets
     * @return array<string, mixed>
     */
    public function applyConfiguredPresets(array $presets): array
    {
        $results = [];
        foreach ($presets as $name => $config) {
            $results[$name] = $this->upsertPreset($name, $config);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function upsertPreset(string $name, array $config): array
    {
        return $this->client->upsertPreset($name, $config);
    }

    /**
     * @return array<string, mixed>
     */
    public function listPresets(): array
    {
        return $this->client->listPresets();
    }

    /**
     * @return array<string, mixed>
     */
    public function retrievePreset(string $name): array
    {
        return $this->client->retrievePreset($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function deletePreset(string $name): array
    {
        return $this->client->deletePreset($name);
    }
}
