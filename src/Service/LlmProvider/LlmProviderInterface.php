<?php

namespace Micka17\TypesenseBundle\Service\LlmProvider;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

interface LlmProviderInterface
{
    public function __construct(
        array $config,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger
    );
    
    public function generateEmbeddings(string $text): array;
    
    public function getModel(): string;
    
    public function getProviderName(): string;
    
    public function getName(): string;
    
    public function getEndpoint(): string;
    
    public function getPayloadTemplate(): array;
    
    public function getResponsePath(): string;
}
