<?php

namespace Micka17\TypesenseBundle\Service;

use Micka17\TypesenseBundle\Attribute\TypesenseField;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use ReflectionClass;

class SchemaGenerator
{
    public function generate(string $entityClass): array
    {
        $reflectionClass = new ReflectionClass($entityClass);
        $indexableAttribute = ($reflectionClass->getAttributes(TypesenseIndexable::class)[0] ?? null)?->newInstance();

        if (!$indexableAttribute) {
            throw new \InvalidArgumentException("L'entité '$entityClass' n'a pas l'attribut #[TypesenseIndexable].");
        }

        $typesenseFields = [];

        foreach ($reflectionClass->getProperties() as $property) {
            if ($property->getName() === 'id') {
                continue;
            }

            $fieldAttribute = ($property->getAttributes(TypesenseField::class)[0] ?? null)?->newInstance();

            if (!$fieldAttribute) {
                continue;
            }

            $fieldName = $fieldAttribute->name ?? $property->getName();

            $fieldDefinition = [
                'name' => $fieldName,
                'type' => $this->determineFieldType($property, $fieldAttribute),
                'facet' => $fieldAttribute->facet,
                'sort' => $fieldAttribute->sort,
                'optional' => $fieldAttribute->optional,
            ];

            $typesenseFields[] = $fieldDefinition;
        }

        $finalFields = array_merge($typesenseFields, $indexableAttribute->nestedFields);

        $schema = [
            'name' => $indexableAttribute->collection,
            'fields' => $finalFields,
        ];
        
        if ($indexableAttribute->defaultSortingField) {
            $isOptional = false;
            foreach ($typesenseFields as $field) {
                if ($field['name'] === $indexableAttribute->defaultSortingField && $field['optional']) {
                    $isOptional = true;
                    break;
                }
            }
            if ($isOptional) {
                 throw new \InvalidArgumentException("Le champ de tri par défaut '{$indexableAttribute->defaultSortingField}' ne peut pas être un champ optionnel.");
            }
            $schema['default_sorting_field'] = $indexableAttribute->defaultSortingField;
        }

        if ($indexableAttribute->enableNestedFields) {
            $schema['enable_nested_fields'] = true;
        }

        return $schema;
    }

    private function determineFieldType(\ReflectionProperty $property, TypesenseField $attribute): string
    {
        if ($attribute->type !== TypesenseField::AUTO_DETECT_TYPE) {
            return $attribute->type;
        }

        $propertyType = $property->getType();
        if ($propertyType instanceof \ReflectionNamedType) {
            $type = $propertyType->getName();
            
            switch ($type) {
                case 'string':
                    return 'string';
                case 'int':
                    return 'int32';
                case 'float':
                    return 'float';
                case 'bool':
                    return 'bool';
                case 'array':
                    return 'string[]';
                default:
                    return 'string';
            }
        }

        return 'string';
    }
}