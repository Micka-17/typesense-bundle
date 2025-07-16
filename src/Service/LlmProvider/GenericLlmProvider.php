<?php

namespace Micka17\TypesenseBundle\Service\LlmProvider;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Micka17\TypesenseBundle\Exception\LlmException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;

class GenericLlmProvider implements LlmProviderInterface
{
    private HttpClientInterface $client;
    private array $config;
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;
    private PropertyAccessorInterface $propertyAccess;

    public function __construct(
        array $config,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->propertyAccess = PropertyAccess::createPropertyAccessor();

        $baseClient = HttpClient::create([
            'headers' => [
                'Authorization' => 'Bearer ' . ($this->config['api_key'] ?? null),
                'Content-Type' => 'application/json',
            ],
            'timeout' => $this->config['timeout'] ?? 30,
        ]);

        $this->client = new RetryableHttpClient(
            $baseClient,
            null,
            $this->config['max_retries'] ?? 3,
            $this->logger
        );
    }

    public function generateEmbeddings(string $text): array
    {
        $cacheKey = 'llm_embedding_' . md5($text);
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        try {
            $payload = $this->buildPayload($text);
            $response = $this->client->request('POST', $this->config['endpoint'], [
                'json' => $payload,
            ]);

            $data = $response->toArray();
            $embeddings = $this->extractEmbeddings($data);

            $cacheItem->set($embeddings);
            $this->cache->save($cacheItem);

            return $embeddings;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération des embeddings: ' . $e->getMessage());
            throw new LlmException('Erreur lors de la génération des embeddings', 0, $e);
        }
    }

    private function buildPayload(string $text): array
    {
        $payload = $this->config['payload'];
        $payload['prompt'] = str_replace('{{text}}', $text, $payload['prompt']);
        return $payload;
    }

    private function extractEmbeddings(array $data): array
    {
        return $this->propertyAccess->getValue($data, $this->config['response_path']);
    }

    public function getModel(): string
    {
        return $this->config['model'] ?? 'unknown';
    }

    public function getProviderName(): string
    {
        return 'generic';
    }

    public function getName(): string
    {
        return 'generic_' . md5($this->config['endpoint']);
    }

    public function getEndpoint(): string
    {
        return $this->config['endpoint'];
    }

    public function getPayloadTemplate(): array
    {
        return $this->config['payload'];
    }

    public function getResponsePath(): string
    {
        return $this->config['response_path'];
    }
}