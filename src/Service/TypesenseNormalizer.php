<?php

namespace Micka17\TypesenseBundle\Service;

use Doctrine\Common\Collections\Collection;
use Micka17\TypesenseBundle\Attribute\TypesenseField;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use ReflectionClass;

class TypesenseNormalizer
{
    public function normalize(object $entity): ?array
    {
        $reflectionClass = new ReflectionClass($entity);
        $indexableAttribute = ($reflectionClass->getAttributes(TypesenseIndexable::class)[0] ?? null)?->newInstance();

        if (!$indexableAttribute) { return null; }

        $documentData = [];

        if ($indexableAttribute->normalizerMethod && method_exists($entity, $indexableAttribute->normalizerMethod)) {
            $methodName = $indexableAttribute->normalizerMethod;
            $documentData = $entity->{$methodName}();
            if (!is_array($documentData)) {
                throw new \LogicException("La méthode '$methodName' de l'entité " . $entity::class . " doit retourner un tableau.");
            }

        } else {
            $nestedConfigMap = [];
            foreach ($indexableAttribute->nestedFields as $config) {
                list($sourceProperty, $targetKey) = explode('.', $config['name'], 2);
                $nestedConfigMap[$sourceProperty][$targetKey] = $config['method'] ?? null;
            }

            foreach ($reflectionClass->getProperties() as $property) {
                $fieldAttribute = ($property->getAttributes(TypesenseField::class)[0] ?? null)?->newInstance();
                if (!$fieldAttribute) { continue; }

                $fieldName = $fieldAttribute->name ?? $property->getName();
                $property->setAccessible(true);
                $value = $property->getValue($entity);

                $nestedConfig = $nestedConfigMap[$fieldName] ?? null;
                $documentData[$fieldName] = $this->formatValue($value, $fieldAttribute, $nestedConfig);
            }
        }
        
        if (method_exists($entity, 'getId') && $entity->getId() !== null) {
            $documentData['id'] = (string) $entity->getId();
        } else {
            return null;
        }

        return [
            'collection' => $indexableAttribute->collection,
            'document' => $documentData
        ];
    }

    private function formatValue(mixed $value, TypesenseField $attribute, ?array $nestedConfig): mixed
    {
        if ($value === null) return null;
        
        if ($value instanceof Collection) {
            return $value->map(fn($item) => $this->normalizeNestedObject($item, $nestedConfig))->toArray();
        }

        if ($value instanceof \DateTimeInterface) return $value->getTimestamp();
        
        if (is_object($value)) {
            if ($attribute->type === 'string' && method_exists($value, 'getId')) {
                return (string) $value->getId();
            }
            return $this->normalizeSimpleObject($value);
        }

        return $value;
    }

    private function normalizeNestedObject(object $item, ?array $config): ?array
    {
        if ($config === null) {
            return $this->normalizeSimpleObject($item);
        }

        $data = [];
        
        if (method_exists($item, 'getId')) {
            $data['id'] = (string) $item->getId();
        }

        foreach ($config as $key => $methodChain) {
            if ($methodChain) {
                $data[$key] = $this->callChainedMethods($item, $methodChain);
            } else {
                $fallbackMethod = 'get' . ucfirst($key);
                if (method_exists($item, $fallbackMethod)) {
                    $data[$key] = $item->{$fallbackMethod}();
                } else {
                    $data[$key] = null;
                }
            }
        }

        return $data;
    }

    
    private function normalizeSimpleObject(object $object): ?array
    {
        if (!method_exists($object, 'getId')) return null;
        $data = ['id' => (string) $object->getId()];
        if (method_exists($object, 'getName')) $data['name'] = $object->getName();
        return $data;
    }

    private function callChainedMethods(object $object, string $methodChain): mixed
    {
        $methods = explode('->', $methodChain);
        $result = $object;
        foreach ($methods as $method) {
            $method = str_replace('()', '', $method);
            if ($result === null) return null;
            if (!method_exists($result, $method)) {
                throw new \InvalidArgumentException("La méthode '$method' n'existe pas sur la classe '" . get_class($result) . "'.");
            }
            $result = $result->{$method}();
        }
        return $result;
    }
}