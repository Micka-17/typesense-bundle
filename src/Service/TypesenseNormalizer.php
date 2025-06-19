<?php
// Fichier : micka-17/typesense-bundle/src/Service/TypesenseNormalizer.php

namespace Micka17\TypesenseBundle\Service;

use Micka17\TypesenseBundle\Attribute\TypesenseField;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use ReflectionClass;
use ReflectionProperty;

class TypesenseNormalizer
{
    public function __construct() {}

    public function normalize(object $entity): ?array
    {
        $reflectionClass = new ReflectionClass($entity);
        $indexableAttribute = ($reflectionClass->getAttributes(TypesenseIndexable::class)[0] ?? null)?->newInstance();

        if (!$indexableAttribute) {
            return null;    
        }

        if (!method_exists($entity, 'getId') || $entity->getId() === null) {
            return null;
        }

        $documentData = [];

        if ($indexableAttribute->normalizerMethod) {
            $method = $indexableAttribute->normalizerMethod;
            if (method_exists($entity, $method)) {
                $documentData = $entity->{$method}();
            }
        } else {
            $documentData = $this->normalizeByFields($entity, $reflectionClass);
        }

        if (empty($documentData)) {
            return null;
        }
        
        $documentData['id'] = (string)$entity->getId();

        return [
            'collection' => $indexableAttribute->collection,
            'data' => $documentData,
        ];
    }

    private function normalizeByFields(object $entity, ReflectionClass $reflectionClass): array
    {
        $data = [];
        foreach ($reflectionClass->getProperties() as $property) {
            $fieldAttribute = ($property->getAttributes(TypesenseField::class)[0] ?? null)?->newInstance();

            if (!$fieldAttribute) {
                continue;
            }

            $fieldName = $fieldAttribute->name ?? $property->getName();
            $data[$fieldName] = $this->getPropertyValue($entity, $property, $fieldAttribute);
        }
        return $data;
    }

    private function getPropertyValue(object $entity, ReflectionProperty $property, TypesenseField $attribute): mixed
    {
        if ($attribute->getter) {
            $methodName = $attribute->getter;
            if (method_exists($entity, $methodName)) {
                return $entity->{$methodName}();
            }
        }
        
        return $property->getValue($entity);
    }
}