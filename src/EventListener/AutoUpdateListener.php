<?php

namespace Micka17\TypesenseBundle\EventListener;

use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use Micka17\TypesenseBundle\Service\TypesenseDispatcher;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Proxy;
use ReflectionClass;

class AutoUpdateListener
{
    /**
     * @param array<class-string, string> $indexedEntities
     */
    public function __construct(
        private readonly bool $isEnabled,
        private readonly array $indexedEntities,
        private readonly TypesenseDispatcher $dispatcher,
    ) {}

    /**
     * @param LifecycleEventArgs<\Doctrine\ORM\EntityManagerInterface> $args
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->handleUpdate($args);
    }

    /**
     * @param LifecycleEventArgs<\Doctrine\ORM\EntityManagerInterface> $args
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->handleUpdate($args);
    }

    /**
     * @param LifecycleEventArgs<\Doctrine\ORM\EntityManagerInterface> $args
     */
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
            $this->dispatcher->dispatchDelete($attribute->collection, (string) $entity->getId());
        }
    }

    /**
     * @param LifecycleEventArgs<\Doctrine\ORM\EntityManagerInterface> $args
     */
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

        $this->dispatcher->dispatchIndex($entity);
    }
}
