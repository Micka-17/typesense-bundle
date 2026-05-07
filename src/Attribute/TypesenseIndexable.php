<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Attribute;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class TypesenseIndexable
{
    /**
     * @param array<string, mixed> $nestedFields
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $collection,
        public ?string $normalizerMethod = null,
        public ?string $defaultSortingField = null,
        public bool $enableNestedFields = false,
        public array $nestedFields = [],
        public array $metadata = [],
        public array $options = []
    ) {}
}
