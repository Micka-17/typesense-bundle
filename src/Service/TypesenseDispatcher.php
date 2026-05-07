<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

use Micka17\TypesenseBundle\Messenger\DeleteDocumentMessage;
use Micka17\TypesenseBundle\Messenger\IndexDocumentMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class TypesenseDispatcher
{
    public function __construct(
        private readonly TypesenseManager $manager,
        private readonly TypesenseNormalizer $normalizer,
        private readonly bool $async,
        private readonly ?MessageBusInterface $bus,
    ) {}

    public function dispatchIndex(object $entity): void
    {
        if ($this->async) {
            $this->requireBus();
            if (!method_exists($entity, 'getId')) {
                return;
            }
            /** @var \Symfony\Component\Messenger\MessageBusInterface $bus */
            $bus = $this->bus;
            $bus->dispatch(new IndexDocumentMessage(get_class($entity), (string) $entity->getId()));
            return;
        }

        $document = $this->normalizer->normalize($entity);
        if ($document !== null) {
            $this->manager->createOrUpdateDocument($document['collection'], $document['document']);
        }
    }

    public function dispatchDelete(string $collectionName, string $documentId): void
    {
        if ($this->async) {
            $this->requireBus();
            /** @var \Symfony\Component\Messenger\MessageBusInterface $bus */
            $bus = $this->bus;
            $bus->dispatch(new DeleteDocumentMessage($collectionName, $documentId));
            return;
        }

        $this->manager->deleteDocument($collectionName, $documentId);
    }

    private function requireBus(): void
    {
        if ($this->bus === null) {
            throw new \RuntimeException(
                'symfony/messenger is required for async auto_update mode. Install it with: composer require symfony/messenger',
            );
        }
    }
}
