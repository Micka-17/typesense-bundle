<?php
// src/TypesenseBundle/Attribute/TypesenseIndexable.php

namespace Micka_17\TypesenseBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class TypesenseIndexable
{
    public function __construct(
        public string $collection,
        public array $fields
    ) {}
}