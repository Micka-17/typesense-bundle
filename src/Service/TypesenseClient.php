<?php
// src/TypesenseBundle/Service/TypesenseClient.php

namespace Micka17\TypesenseBundle\Service;

use Typesense\Client;

class TypesenseClient
{
    private Client $client;

    public function __construct(
        string $apiKey,
        string $host,
        string $port,
        string $protocol
    ) {
        $this->client = new Client([
            'api_key' => $apiKey,
            'nodes' => [
                [
                    'host' => $host,
                    'port' => $port,
                    'protocol' => $protocol,
                ],
            ],
            'connection_timeout_seconds' => 2,
        ]);
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}