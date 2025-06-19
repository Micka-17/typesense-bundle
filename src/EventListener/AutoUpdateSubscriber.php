<?php
// Fichier : micka-17/typesense-bundle/src/EventListener/AutoUpdateListener.php

namespace Micka17\TypesenseBundle\EventListener;

use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use Micka17\TypesenseBundle\Service\TypesenseManager; // On utilisera le Manager, c'est plus propre
use Doctrine\Persistence\Event\LifecycleEventArgs;
use ReflectionClass;

class AutoUpdateListener
{
    // Le constructeur ne change pas
    public function __construct(
        private readonly bool $isEnabled,
        private readonly array $indexedEntities,
        private readonly TypesenseManager $typesenseManager
    ) {}

    // Une méthode pour chaque événement que l'on va déclarer dans services.yaml
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->handleUpdate($args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->handleUpdate($args);
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        if (!$this->isEnabled) {
            return;
        }

        $entity = $args->getObject();
        $entityClass = get_class($entity);

        if (!in_array($entityClass, $this->indexedEntities) || !method_exists($entity, 'getId')) {
            return;
        }

        $reflectionClass = new ReflectionClass($entity);
        $attribute = ($reflectionClass->getAttributes(TypesenseIndexable::class)[0] ?? null)?->newInstance();

        if ($attribute) {
            $this->typesenseManager->deleteDocument($attribute->collection, (string)$entity->getId());
        }
    }

    // Logique mutualisée pour la création et la mise à jour
    private function handleUpdate(LifecycleEventArgs $args): void
    {
        if (!$this->isEnabled) {
            return;
        }

        $entity = $args->getObject();
        if (!in_array(get_class($entity), $this->indexedEntities)) {
            return;
        }

        $document = $this->normalize($entity);
        if ($document) {
            $this->typesenseManager->createOrUpdateDocument($document['collection'], $document['data']);
        }
    }

    // La méthode de normalisation ne change pas
    private function normalize(object $entity): ?array
    {
        $reflectionClass = new ReflectionClass($entity);
        $attributeInstance = ($reflectionClass->getAttributes(TypesenseIndexable::class)[0] ?? null)?->newInstance();

        if (!$attributeInstance || !method_exists($entity, 'getId')) {
            return null;
        }

        $data = ['id' => (string)$entity->getId()];
        foreach ($attributeInstance->fields as $field) {
            $getter = 'get' . ucfirst($field);
            if (method_exists($entity, $getter)) {
                $data[$field] = $entity->{$getter}();
            }
        }

        return [
            'collection' => $attributeInstance->collection,
            'data' => $data,
        ];
    }
}