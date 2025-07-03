<?php

namespace Micka17\TypesenseBundle\Dto;

use JsonSerializable;

readonly class Result implements JsonSerializable
{
    public function __construct(
        public int $found,
        public int $tookMs,
        public array $hits
    ) {}

    public static function fromApiResponse(array $response): self
    {
        return new self(
            $response['found'],
            $response['took_ms'],
            $response['hits']
        );
    }

    public function toArray(): array
    {
        return [
            'found' => $this->found,
            'took_ms' => $this->tookMs,
            'hits' => $this->hits,
        ];
    }
    
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}