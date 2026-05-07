<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

use Micka17\TypesenseBundle\Dto\SchemaDiff;
use Typesense\Exceptions\ObjectNotFound;

class SchemaDiffService
{
    private const METADATA_KEYS = ['default_sorting_field', 'enable_nested_fields'];

    public function __construct(
        private readonly TypesenseClient $client,
        private readonly SchemaGenerator $schemaGenerator,
    ) {}

    public function diff(string $entityClass): SchemaDiff
    {
        $generated = $this->schemaGenerator->generate($entityClass);
        $collectionName = $generated['name'];

        try {
            $live = $this->client->retrieveCollection($collectionName);
        } catch (ObjectNotFound) {
            return new SchemaDiff($collectionName, $generated['fields'] ?? [], [], [], false);
        }

        /** @var array<string, array<string, mixed>> $liveFields */
        $liveFields = [];
        foreach ($live['fields'] ?? [] as $field) {
            $liveFields[$field['name']] = $field;
        }

        /** @var array<string, array<string, mixed>> $generatedFields */
        $generatedFields = [];
        foreach ($generated['fields'] ?? [] as $field) {
            $generatedFields[$field['name']] = $field;
        }

        $fieldsToAdd = [];
        $fieldConflicts = [];

        foreach ($generatedFields as $name => $genField) {
            if (!isset($liveFields[$name])) {
                $fieldsToAdd[] = $genField;
            } elseif (($liveFields[$name]['type'] ?? null) !== $genField['type']) {
                $fieldConflicts[] = [
                    'name' => $name,
                    'live_type' => $liveFields[$name]['type'] ?? 'unknown',
                    'generated_type' => $genField['type'],
                ];
            }
        }

        $fieldsToDrop = [];
        foreach ($liveFields as $name => $_) {
            if ($name === 'id') {
                continue;
            }
            if (!isset($generatedFields[$name])) {
                $fieldsToDrop[] = $name;
            }
        }

        $metadataChanged = false;
        foreach (self::METADATA_KEYS as $key) {
            if (($generated[$key] ?? null) !== ($live[$key] ?? null)) {
                $metadataChanged = true;
                break;
            }
        }

        return new SchemaDiff($collectionName, $fieldsToAdd, $fieldsToDrop, $fieldConflicts, $metadataChanged);
    }

    /**
     * Apply a diff via PATCH (add + drop fields).
     * Does nothing if the diff has no patchable changes or requires recreation.
     */
    public function applyDiff(SchemaDiff $diff): void
    {
        if (!$diff->hasChanges() || $diff->requiresRecreation()) {
            return;
        }

        $fields = $diff->fieldsToAdd;

        foreach ($diff->fieldsToDrop as $fieldName) {
            $fields[] = ['name' => $fieldName, 'drop' => true];
        }

        $this->client->updateCollection($diff->collectionName, $fields);
    }
}
