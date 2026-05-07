<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

class AliasManager
{
    public function __construct(private readonly TypesenseClient $client) {}

    /** @return array<string, mixed> */
    public function upsertAlias(string $name, string $collectionName): array
    {
        return $this->client->upsertAlias($name, $collectionName);
    }

    /** @return array<string, mixed> */
    public function listAliases(): array
    {
        return $this->client->listAliases();
    }

    /** @return array<string, mixed> */
    public function retrieveAlias(string $name): array
    {
        return $this->client->retrieveAlias($name);
    }

    /** @return array<string, mixed> */
    public function deleteAlias(string $name): array
    {
        return $this->client->deleteAlias($name);
    }

    /**
     * Atomically points $aliasName to $newCollection.
     * Returns the previous collection name, or null if the alias did not exist.
     */
    public function swapAlias(string $aliasName, string $newCollection): ?string
    {
        $previous = null;

        try {
            $existing = $this->client->retrieveAlias($aliasName);
            $previous = $existing['collection_name'] ?? null;
        } catch (\Throwable) {
            // alias does not exist yet — that's fine
        }

        $this->client->upsertAlias($aliasName, $newCollection);

        return is_string($previous) ? $previous : null;
    }
}
