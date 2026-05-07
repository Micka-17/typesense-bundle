<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Dto;

class SchemaDiff
{
    /**
     * @param array<int, array<string, mixed>> $fieldsToAdd
     * @param array<int, string>               $fieldsToDrop
     * @param array<int, array<string, mixed>> $fieldConflicts  fields with same name but different type
     */
    public function __construct(
        public readonly string $collectionName,
        public readonly array $fieldsToAdd,
        public readonly array $fieldsToDrop,
        public readonly array $fieldConflicts,
        public readonly bool $metadataChanged,
    ) {}

    public function hasChanges(): bool
    {
        return $this->fieldsToAdd !== []
            || $this->fieldsToDrop !== []
            || $this->fieldConflicts !== []
            || $this->metadataChanged;
    }

    /** True when the diff cannot be applied via PATCH and requires a full recreation. */
    public function requiresRecreation(): bool
    {
        return $this->fieldConflicts !== [] || $this->metadataChanged;
    }
}
