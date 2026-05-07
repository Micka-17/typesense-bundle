<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Dto;

use JsonSerializable;

class Result implements JsonSerializable
{
    /**
     * @param array<int, array<string, mixed>>   $hits
     * @param array<int, array<string, mixed>>   $facetCounts
     * @param array<string, mixed>|null          $conversation
     */
    public function __construct(
        public readonly int $found,
        public readonly int $tookMs,
        public readonly array $hits,
        public readonly array $facetCounts = [],
        public readonly ?string $searchCutoff = null,
        public readonly ?array $conversation = null,
    ) {}

    /**
     * @param array<string, mixed> $response
     */
    public static function fromApiResponse(array $response): self
    {
        return new self(
            found: (int) ($response['found'] ?? 0),
            tookMs: (int) ($response['took_ms'] ?? 0),
            hits: $response['hits'] ?? [],
            facetCounts: $response['facet_counts'] ?? [],
            searchCutoff: isset($response['search_cutoff']) ? (string) $response['search_cutoff'] : null,
            conversation: $response['conversation'] ?? null,
        );
    }

    public bool $isEmpty {
        get => $this->found === 0;
    }

    public bool $isConversational {
        get => $this->conversation !== null;
    }

    public ?string $conversationAnswer {
        get => $this->conversation['answer'] ?? null;
    }

    public ?string $conversationId {
        get => $this->conversation['conversation_id'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'found' => $this->found,
            'took_ms' => $this->tookMs,
            'hits' => $this->hits,
            'facet_counts' => $this->facetCounts,
        ];

        if ($this->conversation !== null) {
            $data['conversation'] = $this->conversation;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
