<?php
// src/TypesenseBundle/Service/TypesenseNormalizer.php

namespace Micka17\TypesenseBundle\Service;

use Doctrine\Persistence\ManagerRegistry;
use Micka17\TypesenseBundle\Attribute\TypesenseField;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use ReflectionClass;
use ReflectionProperty;

class TypesenseNormalizer
{
    public function __construct(private readonly ManagerRegistry $doctrine)
    {
    }

    public function normalize(object $entity): ?array
    {
        $reflectionClass = new ReflectionClass($entity);
        $indexableAttribute = ($reflectionClass->getAttributes(TypesenseIndexable::class)[0] ?? null)?->newInstance();

        if (!$indexableAttribute) {
            return null;
        }

        $documentData = [];
        // L'ID est un cas spécial et obligatoire pour Typesense
        if (method_exists($entity, 'getId')) {
            $documentData['id'] = (string)$entity->getId();
        } else {
            // Ne pas indexer une entité sans ID
            return null;
        }

        // On parcourt toutes les propriétés de l'entité
        foreach ($reflectionClass->getProperties() as $property) {
            // On cherche l'attribut #[TypesenseField]
            $fieldAttribute = ($property->getAttributes(TypesenseField::class)[0] ?? null)?->newInstance();

            if (!$fieldAttribute) {
                continue; // Si pas d'attribut, on ignore cette propriété
            }

            $fieldName = $fieldAttribute->name ?? $property->getName();
            $value = $this->getPropertyValue($entity, $property, $fieldAttribute);

            $documentData[$fieldName] = $value;
        }

        return [
            'collection' => $indexableAttribute->collection,
            'data' => $documentData,
        ];
    }

    private function getPropertyValue(object $entity, ReflectionProperty $property, TypesenseField $attribute): mixed
    {
        // NOUVELLE LOGIQUE : on vérifie si un 'getter' personnalisé est défini
        if ($attribute->getter) {
            $methodName = $attribute->getter;
            if (method_exists($entity, $methodName)) {
                // Si la méthode existe, on l'appelle pour avoir la valeur
                return $entity->{$methodName}();
            }
        }

        // Logique standard : on accède à la propriété (elle doit être publique ou avoir un getter)
        // Pour simplifier, on utilise la réflexion pour accéder à la valeur, même privée.
        return $property->getValue($entity);
    }
}