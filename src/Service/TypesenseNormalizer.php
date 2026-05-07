<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

use Doctrine\Common\Collections\Collection;
use Micka17\TypesenseBundle\Attribute\TypesenseField;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use ReflectionClass;
use ReflectionProperty;

class TypesenseNormalizer
{
    /** @return array<string, mixed>|null */
    public function normalize(object $entity): ?array
    {
        $reflectionClass = new ReflectionClass($entity);
        $indexableAttribute = ($reflectionClass->getAttributes(TypesenseIndexable::class)[0] ?? null)?->newInstance();

        if (!$indexableAttribute) {
            return null;
        }

        if ($indexableAttribute->normalizerMethod && method_exists($entity, $indexableAttribute->normalizerMethod)) {
            $methodName = $indexableAttribute->normalizerMethod;
            $documentData = $entity->{$methodName}();
            if (!is_array($documentData)) {
                throw new \LogicException(
                    "La méthode '$methodName' de l'entité " . $entity::class . " doit retourner un tableau.",
                );
            }
        } else {
            $nestedConfigMap = [];
            foreach ($indexableAttribute->nestedFields as $config) {
                [$sourceProperty, $targetKey] = explode('.', $config['name'], 2);
                $nestedConfigMap[$sourceProperty][$targetKey] = $config['method'] ?? null;
            }

            $documentData = [];
            foreach ($reflectionClass->getProperties() as $property) {
                $fieldAttribute = ($property->getAttributes(TypesenseField::class)[0] ?? null)?->newInstance();
                if (!$fieldAttribute) {
                    continue;
                }

                // Auto-embed fields: Typesense generates the vector server-side — do not send the field.
                if ($fieldAttribute->embed !== null) {
                    continue;
                }

                $fieldName = $fieldAttribute->name ?? $property->getName();
                $value = $this->getValue($entity, $property, $fieldAttribute);
                $nestedConfig = $nestedConfigMap[$fieldName] ?? null;

                $documentData[$fieldName] = $this->formatValue($value, $fieldAttribute, $nestedConfig);
            }
        }

        if (!method_exists($entity, 'getId') || $entity->getId() === null) {
            return null;
        }

        $documentData['id'] = (string) $entity->getId();

        return [
            'collection' => $indexableAttribute->collection,
            'document' => $documentData,
        ];
    }

    // --- Value extraction ---

    private function getValue(object $entity, ReflectionProperty $property, TypesenseField $attribute): mixed
    {
        if ($attribute->getter !== null && method_exists($entity, $attribute->getter)) {
            return $entity->{$attribute->getter}();
        }

        return $property->getValue($entity);
    }

    // --- Value formatting ---

    /** @param array<string, mixed>|null $nestedConfig */
    private function formatValue(mixed $value, TypesenseField $attribute, ?array $nestedConfig): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if ($value instanceof Collection) {
            return array_values(
                $value->map(fn($item) => $this->normalizeRelatedObject($item, $attribute, $nestedConfig))->toArray(),
            );
        }

        // Reference field: resolve the related entity to its ID string.
        if ($attribute->reference !== null && is_object($value)) {
            return $this->resolveToId($value);
        }

        if (is_object($value)) {
            // type === 'string' with an object → resolve to ID (e.g. enum-based IDs)
            if ($attribute->type === 'string' && method_exists($value, 'getId')) {
                return (string) $value->getId();
            }

            return $this->normalizeObject($value, $attribute, $nestedConfig);
        }

        if (is_array($value)) {
            return $this->formatArrayValue($value, $attribute);
        }

        return $this->coerceScalar($value, $attribute->type);
    }

    // --- Arrays ---

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private function formatArrayValue(array $value, TypesenseField $attribute): array
    {
        // Raw vector array (float[]) or pre-built object arrays: pass through as-is.
        if ($attribute->numDim !== null || $attribute->type === 'float[]') {
            return array_map(static fn($v) => (float) $v, $value);
        }

        $elementType = match (true) {
            str_ends_with($attribute->type, '[]') => substr($attribute->type, 0, -2),
            default => null,
        };

        if ($elementType === null) {
            return $value;
        }

        return array_values(array_map(
            fn($item) => is_scalar($item) ? $this->coerceScalar($item, $elementType) : $item,
            $value,
        ));
    }

    // --- Object normalization ---

    /** @param array<string, mixed>|null $nestedConfig */
    private function normalizeRelatedObject(object $item, TypesenseField $attribute, ?array $nestedConfig): mixed
    {
        // Reference field → only the ID
        if ($attribute->reference !== null) {
            return $this->resolveToId($item);
        }

        return $this->normalizeObject($item, $attribute, $nestedConfig);
    }

    /** @param array<string, mixed>|null $nestedConfig */
    private function normalizeObject(object $object, TypesenseField $attribute, ?array $nestedConfig): mixed
    {
        // If the related class is itself annotated with TypesenseField, normalize recursively.
        if ($this->hasTypesenseFields($object)) {
            return $this->normalizeObjectWithAttributes($object);
        }

        if ($nestedConfig !== null) {
            return $this->normalizeWithConfig($object, $nestedConfig);
        }

        // object type → return null for type safety (caller must use a normalizerMethod)
        if ($attribute->type === 'object' || $attribute->type === 'object[]') {
            return null;
        }

        return $this->normalizeFallback($object);
    }

    private function hasTypesenseFields(object $object): bool
    {
        $rc = new ReflectionClass($object);
        return array_any(
            $rc->getProperties(),
            static fn(ReflectionProperty $p) => $p->getAttributes(TypesenseField::class) !== [],
        );
    }

    /** @return array<string, mixed> */
    private function normalizeObjectWithAttributes(object $object): array
    {
        $rc = new ReflectionClass($object);
        $data = [];

        foreach ($rc->getProperties() as $property) {
            $fa = ($property->getAttributes(TypesenseField::class)[0] ?? null)?->newInstance();
            if (!$fa || $fa->embed !== null) {
                continue;
            }

            $fieldName = $fa->name ?? $property->getName();
            $value = $this->getValue($object, $property, $fa);
            $data[$fieldName] = $this->formatValue($value, $fa, null);
        }

        if (method_exists($object, 'getId') && $object->getId() !== null) {
            $data['id'] = (string) $object->getId();
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function normalizeWithConfig(object $item, array $config): array
    {
        $data = [];

        if (method_exists($item, 'getId')) {
            $data['id'] = (string) $item->getId();
        }

        foreach ($config as $key => $methodChain) {
            if ($methodChain) {
                $data[$key] = $this->callChainedMethods($item, $methodChain);
            } else {
                $fallbackMethod = 'get' . ucfirst($key);
                $data[$key] = method_exists($item, $fallbackMethod) ? $item->{$fallbackMethod}() : null;
            }
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    private function normalizeFallback(object $object): ?array
    {
        if (!method_exists($object, 'getId')) {
            return null;
        }

        $data = ['id' => (string) $object->getId()];

        if (method_exists($object, 'getName')) {
            $data['name'] = $object->getName();
        }

        return $data;
    }

    // --- Helpers ---

    private function resolveToId(object $object): ?string
    {
        if (method_exists($object, 'getId') && $object->getId() !== null) {
            return (string) $object->getId();
        }

        return null;
    }

    private function coerceScalar(mixed $value, string $type): mixed
    {
        return match (true) {
            in_array($type, ['int32', 'int64'], true) => (int) $value,
            $type === 'float' => (float) $value,
            $type === 'bool' => (bool) $value,
            $type === 'string' && is_scalar($value) => (string) $value,
            default => $value,
        };
    }

    private function callChainedMethods(object $object, string $methodChain): mixed
    {
        $result = $object;
        foreach (explode('->', $methodChain) as $method) {
            $method = str_replace('()', '', $method);
            if ($result === null) {
                return null;
            }
            if (!method_exists($result, $method)) {
                throw new \InvalidArgumentException(
                    "La méthode '$method' n'existe pas sur la classe '" . get_class($result) . "'.",
                );
            }
            $result = $result->{$method}();
        }

        return $result;
    }
}
