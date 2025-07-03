<?php

namespace Micka17\TypesenseBundle\Helper;

class SchemaBuilder
{
    private array $schema = [];

    public function __construct(string $name)
    {
        $this->schema['name'] = $name;
    }

    public static function create(string $name): self
    {
        return new self($name);
    }
    
    public function addField(string $name, string $type, bool $facet = false, bool $optional = false): self
    {
        $this->schema['fields'][] = [
            'name' => $name,
            'type' => $type,
            'facet' => $facet,
            'optional' => $optional
        ];
        return $this;
    }

    public function defaultSortingField(string $fieldName): self
    {
        $this->schema['default_sorting_field'] = $fieldName;
        return $this;
    }

    public function build(): array
    {
        return $this->schema;
    }
}