<?php

namespace Micka17\TypesenseBundle\EventListener;

use Micka17\TypesenseBundle\Service\TypesenseManager;
use Micka17\TypesenseBundle\Service\TypesenseNormalizer;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Proxy;
use ReflectionClass;

class AutoUpdateListener
{
    public function __construct(
        private readonly bool $isEnabled,
        private readonly array $indexedEntities,
        private readonly TypesenseManager $typesenseManager,
        private readonly TypesenseNormalizer $typesenseNormalizer
    ) {}

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

        if ($entity instanceof Proxy) {
            $entityClass = get_parent_class($entity);
        }

        if (!in_array($entityClass, $this->indexedEntities) || !method_exists($entity, 'getId')) {
            return;
        }

        $reflectionClass = new ReflectionClass($entityClass);
        $attribute = ($reflectionClass->getAttributes(TypesenseIndexable::class)[0] ?? null)?->newInstance();

        if ($attribute) {
            $this->typesenseManager->deleteDocument($attribute->collection, (string)$entity->getId());
        }
    }

    private function handleUpdate(LifecycleEventArgs $args): void
    {
        if (!$this->isEnabled) {
            return;
        }

        $entity = $args->getObject();
        $entityClass = get_class($entity);

        if ($entity instanceof Proxy) {
            $entityClass = get_parent_class($entity);
        }

        if (!in_array($entityClass, $this->indexedEntities)) {
            return;
        }

        $document = $this->typesenseNormalizer->normalize($entity);

        if ($document) {
            $this->typesenseManager->createOrUpdateDocument($document['collection'], $document['document']);
        }
    }
}