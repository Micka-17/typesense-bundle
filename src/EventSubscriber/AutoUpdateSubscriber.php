<?php
// src/TypesenseBundle/EventSubscriber/AutoUpdateSubscriber.php

namespace Micka17\TypesenseBundle\EventSubscriber;

use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use Micka17\TypesenseBundle\Service\TypesenseManager;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use ReflectionClass;

class AutoUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly bool $isEnabled,
        private readonly array $indexedEntities,
        private readonly TypesenseManager $typesenseManager
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::preRemove,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->handleEvent($args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->handleEvent($args);
    }
    
    private function handleEvent(LifecycleEventArgs $args): void
    {
        if (!$this->isEnabled) {
            return;
        }

        $entity = $args->getObject();
        $entityClass = get_class($entity);

        if (!in_array($entityClass, $this->indexedEntities)) {
            return;
        }
        
        $document = $this->normalize($entity);
        if ($document) {
            $this->typesenseManager->createOrUpdateDocument($document['collection'], $document['data']);
        }
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
        $attribute = $reflectionClass->getAttributes(TypesenseIndexable::class)[0] ?? null;

        if ($attribute) {
            $collectionName = $attribute->newInstance()->collection;
            $this->typesenseManager->deleteDocument($collectionName, (string)$entity->getId());
        }
    }
    
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
                $data[$field] = $entity->$getter();
            }
        }
        
        return [
            'collection' => $attributeInstance->collection,
            'data' => $data
        ];
    }
}