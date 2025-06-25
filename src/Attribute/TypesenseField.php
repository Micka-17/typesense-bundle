<?php

namespace Micka17\TypesenseBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class TypesenseField
{
    public const AUTO_DETECT_TYPE = 'auto';

    public function __construct(
        public ?string $name = null,
        public string $type = self::AUTO_DETECT_TYPE,
        public ?string $getter = null,

        public bool $facet = false,
        public bool $optional = false,
        public bool $sort = false
    ) {}
}