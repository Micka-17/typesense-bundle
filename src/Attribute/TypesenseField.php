<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class TypesenseField
{
    public const AUTO_DETECT_TYPE = 'auto';

    /**
     * Valid Typesense field types.
     * Used by SchemaGenerator for validation.
     */
    public const VALID_TYPES = [
        'string', 'string[]',
        'int32', 'int32[]',
        'int64', 'int64[]',
        'float', 'float[]',
        'bool', 'bool[]',
        'geopoint', 'geopoint[]',
        'object', 'object[]',
        'image',
        'auto',
    ];

    /**
     * @param array<int, string>         $tokenSeparators
     * @param array<int, string>         $symbolsToIndex
     * @param array<string, mixed>|null  $embed
     * @param array<string, mixed>|null  $hnswParams
     * @param array<string, mixed>       $options
     */
    public function __construct(
        public ?string $name = null,
        public string $type = self::AUTO_DETECT_TYPE,
        public ?string $getter = null,

        // Core indexing flags
        public bool $facet = false,
        public bool $optional = false,
        public bool $sort = false,
        public bool $index = true,

        // JOINs (Typesense v0.25+)
        public ?string $reference = null,
        public ?bool $asyncReference = null,
        public ?bool $cascadeDelete = null,

        // String options
        public ?int $truncate = null,
        public array $tokenSeparators = [],
        public array $symbolsToIndex = [],

        // Vector / embedding (Typesense v0.25+)
        public ?array $embed = null,
        public ?int $numDim = null,
        public ?string $vecDist = null,
        public ?array $hnswParams = null,

        // Escape hatch for future Typesense options
        public array $options = [],
    ) {}
}
