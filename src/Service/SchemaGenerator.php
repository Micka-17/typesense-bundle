<?php
// Fichier : micka-17/typesense-bundle/src/Service/SchemaGenerator.php

namespace Micka17\TypesenseBundle\Service;

use Micka17\TypesenseBundle\Attribute\TypesenseField;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use ReflectionClass;
use Symfony\Component\PropertyInfo\Type; // Pour une future détection de type auto

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
        // On ajoute le champ 'id' qui est implicitement requis
        $typesenseFields[] = ['name' => 'id', 'type' => 'string'];

        foreach ($reflectionClass->getProperties() as $property) {
            $fieldAttribute = ($property->getAttributes(TypesenseField::class)[0] ?? null)?->newInstance();

            if (!$fieldAttribute) {
                continue; // On ignore les propriétés non annotées
            }

            $fieldName = $fieldAttribute->name ?? $property->getName();

            // Si l'id de l'entité est aussi un champ, on l'ignore car déjà ajouté
            if ($fieldName === 'id') {
                continue;
            }

            $fieldDefinition = [
                'name' => $fieldName,
                'type' => $this->determineFieldType($property, $fieldAttribute),
                'facet' => $fieldAttribute->facet,
                'optional' => $fieldAttribute->optional,
            ];

            $typesenseFields[] = $fieldDefinition;
        }

        return [
            'name' => $indexableAttribute->collection,
            'fields' => $typesenseFields,
            'default_sorting_field' => 'id'
        ];
    }

    private function determineFieldType(\ReflectionProperty $property, TypesenseField $attribute): string
    {
        if ($attribute->type !== TypesenseField::AUTO_DETECT_TYPE) {
            return $attribute->type;
        }

        // Logique de détection automatique de base
        $propertyType = $property->getType();
        if ($propertyType instanceof \ReflectionNamedType) {
            switch ($propertyType->getName()) {
                case 'string':
                case 'array': // Un tableau simple sera un tableau de string par défaut
                    return 'string[]';
                case 'int':
                    return 'int32';
                case 'float':
                    return 'float';
                case 'bool':
                    return 'bool';
            }
        }

        // Par défaut, on retourne 'auto' pour que Typesense décide
        return 'auto';
    }
}