<?php
namespace Micka17\TypesenseBundle\Attribute;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class TypesenseIndexable
{
    public function __construct(
        public string $collection,
        public ?string $normalizerMethod = null,
        public ?string $defaultSortingField = null,
        public bool $enableNestedFields = false,
        public array $nestedFields = []
    ) {}
}