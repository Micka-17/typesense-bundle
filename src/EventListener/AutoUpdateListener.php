<?php
// Fichier : micka-17/typesense-bundle/src/EventListener/AutoUpdateListener.php

namespace Micka17\TypesenseBundle\EventListener;

use Micka17\TypesenseBundle\Service\TypesenseManager;
use Micka17\TypesenseBundle\Service\TypesenseNormalizer; // <-- Importer le normalizer
use Doctrine\Persistence\Event\LifecycleEventArgs;
// ...

class AutoUpdateListener
{
    public function __construct(
        private readonly bool $isEnabled,
        private readonly array $indexedEntities,
        private readonly TypesenseManager $typesenseManager,
        private readonly TypesenseNormalizer $normalizer // <-- Injecter le normalizer
    ) {}

    // ... les autres méthodes (postUpdate, preRemove...) ne changent pas ...

    private function handleUpdate(LifecycleEventArgs $args): void
    {
        // ... la première partie de la méthode ne change pas ...
        $entity = $args->getObject();
        // ... gestion des proxys ...
        if (!in_array($entityClass, $this->indexedEntities)) {
            return;
        }

        // On utilise maintenant notre service pour faire le travail
        $document = $this->normalizer->normalize($entity); // <-- APPEL AU NORMALIZER

        if ($document) {
            $this->typesenseManager->createOrUpdateDocument($document['collection'], $document['data']);
        }
    }
}