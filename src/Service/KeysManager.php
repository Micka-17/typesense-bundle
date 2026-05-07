<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

class KeysManager
{
    public function __construct(private readonly TypesenseClient $client) {}

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>  Contains 'value' key — only returned once at creation time.
     */
    public function createKey(array $config): array
    {
        return $this->client->createKey($config);
    }

    /** @return array<string, mixed> */
    public function listKeys(): array
    {
        return $this->client->listKeys();
    }

    /** @return array<string, mixed> */
    public function retrieveKey(int $id): array
    {
        return $this->client->retrieveKey($id);
    }

    /** @return array<string, mixed> */
    public function deleteKey(int $id): array
    {
        return $this->client->deleteKey($id);
    }
}
