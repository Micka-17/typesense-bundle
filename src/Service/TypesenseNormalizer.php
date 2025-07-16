<?php

namespace Micka17\TypesenseBundle\Service;

use Doctrine\Common\Collections\Collection;
use Micka17\TypesenseBundle\Attribute\TypesenseField;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use ReflectionClass;
use InvalidArgumentException;
use Micka17\TypesenseBundle\Service\LlmService;

class TypesenseNormalizer
{
    public function __construct(
        private LlmService $llmService
    ) {}
    
    public function normalize(object $entity): ?array
    {
        $reflectionClass = new ReflectionClass($entity);
        $indexableAttribute = ($reflectionClass->getAttributes(TypesenseIndexable::class)[0] ?? null)?->newInstance();

        if (!$indexableAttribute) { return null; }

        $documentData = [];

        foreach ($reflectionClass->getProperties() as $property) {
            $fieldAttribute = ($property->getAttributes(TypesenseField::class)[0] ?? null)?->newInstance();
            if (!$fieldAttribute) { continue; }

            $fieldName = $fieldAttribute->name ?? $property->getName();
            $property->setAccessible(true);
            $value = $property->getValue($entity);

            $documentData[$fieldName] = $this->formatSimpleValue($value, $fieldAttribute);
        }

        $nestedFieldConfigs = $this->groupNestedFields($indexableAttribute->nestedFields);

        foreach ($nestedFieldConfigs as $baseProperty => $configs) {
            if (!property_exists($entity, $baseProperty)) continue;
            
            $basePropertyRef = $reflectionClass->getProperty($baseProperty);
            $basePropertyRef->setAccessible(true);
            $collection = $basePropertyRef->getValue($entity);

            if ($collection instanceof Collection) {
                $documentData[$baseProperty] = $collection->map(fn($item) => $this->normalizeNestedItem($item, $configs))->toArray();
            }
        }

        if ($this->llmService->isEnabled() && !empty($indexableAttribute->embeddingFields)) {
            $textParts = [];
            foreach ($indexableAttribute->embeddingFields as $fieldName) {
                if (isset($documentData[$fieldName]) && is_scalar($documentData[$fieldName])) {
                    $textParts[] = $documentData[$fieldName];
                }
            }

            $textToEmbed = implode('. ', $textParts);

            if (!empty($textToEmbed)) {
                $vector = $this->llmService->generateEmbeddings($textToEmbed);
                $documentData['embedding_vector'] = $vector;
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

    private function groupNestedFields(array $nestedFields): array
    {
        $grouped = [];
        foreach ($nestedFields as $field) {
            if (!isset($field['name']) || !str_contains($field['name'], '.')) continue;

            list($baseProperty, $subKey) = explode('.', $field['name'], 2);
            $grouped[$baseProperty][] = [
                'target_key' => $subKey,
                'method' => $field['method'] ?? null,
            ];
        }
        return $grouped;
    }
    
    private function normalizeNestedItem(object $item, array $configs): array
    {
        $data = [];
        foreach ($configs as $config) {
            $key = $config['target_key'];
            $methodChain = $config['method'];

            if ($methodChain) {
                $data[$key] = $this->callChainedMethods($item, $methodChain);
            } else {
                $fallbackMethod = 'get' . ucfirst($key);
                $data[$key] = method_exists($item, $fallbackMethod) ? $item->{$fallbackMethod}() : null;
            }
        }
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
                throw new InvalidArgumentException("La méthode '$method' n'existe pas sur la classe '" . get_class($result) . "'.");
            }
            $result = $result->{$method}();
        }
        return $result;
    }

    private function formatSimpleValue(mixed $value, TypesenseField $attribute): mixed
    {
        if ($value === null) return null;
        if ($value instanceof \DateTimeInterface) return $value->getTimestamp();
        
        if (is_object($value) && !$value instanceof Collection) {
            if ($attribute->type === 'string' && method_exists($value, 'getId')) {
                return (string) $value->getId();
            }
        }

        return $value;
    }
}