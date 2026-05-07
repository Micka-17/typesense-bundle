<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

use Micka17\TypesenseBundle\Attribute\TypesenseField;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class SchemaGenerator
{
    /** Types accepted as default_sorting_field. */
    private const SORTING_FIELD_TYPES = ['int32', 'int64', 'float'];

    /** Types on which sort:true is meaningful. */
    private const SORTABLE_TYPES = ['string', 'int32', 'int64', 'float'];

    /** Types on which string-specific options make sense. */
    private const STRING_TYPES = ['string', 'string[]', 'auto'];

    /** Valid values for vec_dist. */
    private const VALID_VEC_DIST = ['cosine', 'ip', 'l2sq'];

    /** @return array<string, mixed> */
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
            $type = $this->determineFieldType($property, $fieldAttribute);

            $this->validateField($fieldName, $type, $fieldAttribute);

            $field = array_merge(
                [
                    'name' => $fieldName,
                    'type' => $type,
                    'facet' => $fieldAttribute->facet,
                    'sort' => $fieldAttribute->sort,
                    'optional' => $fieldAttribute->optional,
                ],
                $this->buildAdvancedFieldOptions($fieldAttribute),
            );

            $typesenseFields[] = $field;
        }

        $finalFields = array_merge($typesenseFields, $indexableAttribute->nestedFields);

        $schema = [
            'name' => $indexableAttribute->collection,
            'fields' => $finalFields,
        ];

        if ($indexableAttribute->defaultSortingField) {
            $this->validateDefaultSortingField($indexableAttribute->defaultSortingField, $typesenseFields);
            $schema['default_sorting_field'] = $indexableAttribute->defaultSortingField;
        }

        if ($indexableAttribute->enableNestedFields) {
            $schema['enable_nested_fields'] = true;
        }

        if ($indexableAttribute->metadata !== []) {
            $schema['metadata'] = $indexableAttribute->metadata;
        }

        return array_merge($schema, $indexableAttribute->options);
    }

    // --- Validation ---

    private function validateField(string $fieldName, string $type, TypesenseField $attribute): void
    {
        // cascade_delete requires reference.
        if ($attribute->cascadeDelete !== null && $attribute->reference === null) {
            throw new \InvalidArgumentException(
                "Field '$fieldName': 'cascade_delete' requires 'reference' to be set.",
            );
        }

        // async_reference requires reference.
        if ($attribute->asyncReference !== null && $attribute->reference === null) {
            throw new \InvalidArgumentException(
                "Field '$fieldName': 'async_reference' requires 'reference' to be set.",
            );
        }

        // reference format must be "collection.field".
        if ($attribute->reference !== null && !str_contains($attribute->reference, '.')) {
            throw new \InvalidArgumentException(
                "Field '$fieldName': 'reference' must use the 'collection.field' format (got: '{$attribute->reference}').",
            );
        }

        // truncate only valid for string fields.
        if ($attribute->truncate !== null && !in_array($type, self::STRING_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Field '$fieldName' (type: $type): 'truncate' is only valid for string fields.",
            );
        }

        // token_separators only valid for string fields.
        if ($attribute->tokenSeparators !== [] && !in_array($type, self::STRING_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Field '$fieldName' (type: $type): 'token_separators' is only valid for string fields.",
            );
        }

        // symbols_to_index only valid for string fields.
        if ($attribute->symbolsToIndex !== [] && !in_array($type, self::STRING_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Field '$fieldName' (type: $type): 'symbols_to_index' is only valid for string fields.",
            );
        }

        // vec_dist only valid when numDim or embed is present.
        if ($attribute->vecDist !== null && $attribute->numDim === null && $attribute->embed === null) {
            throw new \InvalidArgumentException(
                "Field '$fieldName': 'vec_dist' requires 'num_dim' or 'embed' to be set.",
            );
        }

        // vec_dist must be a known value.
        if ($attribute->vecDist !== null && !in_array($attribute->vecDist, self::VALID_VEC_DIST, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Field '$fieldName': 'vec_dist' must be one of [%s] (got: '%s').",
                    implode(', ', self::VALID_VEC_DIST),
                    $attribute->vecDist,
                ),
            );
        }

        // sort on a non-sortable type.
        if ($attribute->sort && !in_array($type, self::SORTABLE_TYPES, true) && $type !== 'auto') {
            throw new \InvalidArgumentException(
                sprintf(
                    "Field '$fieldName' (type: $type): 'sort: true' is only valid for [%s].",
                    implode(', ', self::SORTABLE_TYPES),
                ),
            );
        }

        // hnsw_params requires numDim or embed.
        if ($attribute->hnswParams !== null && $attribute->numDim === null && $attribute->embed === null) {
            throw new \InvalidArgumentException(
                "Field '$fieldName': 'hnsw_params' requires 'num_dim' or 'embed' to be set.",
            );
        }
    }

    /** @param array<int, array<string, mixed>> $typesenseFields */
    private function validateDefaultSortingField(string $fieldName, array $typesenseFields): void
    {
        $field = array_find(
            $typesenseFields,
            static fn(array $f) => $f['name'] === $fieldName,
        );

        if ($field === null) {
            throw new \InvalidArgumentException(
                "Le champ de tri par défaut '$fieldName' n'existe pas dans les champs définis.",
            );
        }

        if ($field['optional']) {
            throw new \InvalidArgumentException(
                "Le champ de tri par défaut '$fieldName' ne peut pas être un champ optionnel.",
            );
        }

        if (!in_array($field['type'], self::SORTING_FIELD_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Le champ de tri par défaut '$fieldName' doit être de type [%s] (type actuel: '%s').",
                    implode(', ', self::SORTING_FIELD_TYPES),
                    $field['type'],
                ),
            );
        }
    }

    // --- Field options builder ---

    /** @return array<string, mixed> */
    private function buildAdvancedFieldOptions(TypesenseField $attribute): array
    {
        $options = $attribute->options;

        // index: false must be explicit (default is true, omit to save bandwidth).
        if (!$attribute->index) {
            $options['index'] = false;
        }

        $this->setIfNotNull($options, 'reference', $attribute->reference);
        $this->setIfNotNull($options, 'async_reference', $attribute->asyncReference);
        // false must be sent explicitly to disable cascade on JOIN fields; null means "omit from schema".
        $this->setIfNotNull($options, 'cascade_delete', $attribute->cascadeDelete);
        $this->setIfNotNull($options, 'truncate', $attribute->truncate);
        $this->setIfNotEmpty($options, 'token_separators', $attribute->tokenSeparators);
        $this->setIfNotEmpty($options, 'symbols_to_index', $attribute->symbolsToIndex);
        $this->setIfNotNull($options, 'embed', $attribute->embed);
        $this->setIfNotNull($options, 'num_dim', $attribute->numDim);
        $this->setIfNotNull($options, 'vec_dist', $attribute->vecDist);
        $this->setIfNotNull($options, 'hnsw_params', $attribute->hnswParams);

        return $options;
    }

    /** @param array<string, mixed> $options */
    private function setIfNotNull(array &$options, string $key, mixed $value): void
    {
        if ($value !== null) {
            $options[$key] = $value;
        }
    }

    /**
     * @param array<string, mixed> $options
     * @param array<mixed>         $value
     */
    private function setIfNotEmpty(array &$options, string $key, array $value): void
    {
        if ($value !== []) {
            $options[$key] = $value;
        }
    }

    // --- Type detection ---

    private function determineFieldType(ReflectionProperty $property, TypesenseField $attribute): string
    {
        // Auto-embed fields must be float[] — Typesense stores the generated vector.
        if ($attribute->embed !== null && $attribute->type === TypesenseField::AUTO_DETECT_TYPE) {
            return 'float[]';
        }

        if ($attribute->type !== TypesenseField::AUTO_DETECT_TYPE) {
            return $attribute->type;
        }

        $propertyType = $property->getType();

        if (!$propertyType instanceof ReflectionNamedType) {
            return 'string';
        }

        return match ($propertyType->getName()) {
            'string' => 'string',
            'int' => 'int32',
            'float' => 'float',
            'bool' => 'bool',
            'array' => 'string[]',
            default => 'string',
        };
    }
}
