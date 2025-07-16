<?php

namespace Micka17\TypesenseBundle\Service;

use Micka17\TypesenseBundle\Exception\LlmException;
use Micka17\TypesenseBundle\Service\LlmProvider\LlmProviderInterface;

class LlmService
{
    public function __construct(
        private bool $enabled,
        private ?LlmProviderInterface $provider
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled && $this->provider !== null;
    }

    public function generateEmbeddings(string $text): array
    {
        if (!$this->isEnabled()) {
            throw new LlmException('Le service LLM n\'est pas activé ou le fournisseur est mal configuré.');
        }

        return $this->provider->generateEmbeddings($text);
    }

    public function getProvider(): ?LlmProviderInterface
    {
        return $this->provider;
    }
}