<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class TypesenseSynonym
{
    /**
     * @param array<int, string> $synonyms
     */
    public function __construct(
        public string $id,
        public array $synonyms,
        public ?string $root = null
    ) {
    }
}
