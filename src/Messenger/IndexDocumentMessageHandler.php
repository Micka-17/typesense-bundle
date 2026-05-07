<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Micka17\TypesenseBundle\Service\TypesenseManager;
use Micka17\TypesenseBundle\Service\TypesenseNormalizer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class IndexDocumentMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TypesenseNormalizer $normalizer,
        private readonly TypesenseManager $manager,
    ) {}

    public function __invoke(IndexDocumentMessage $message): void
    {
        $entity = $this->em->find($message->entityClass, $message->entityId);

        if ($entity === null) {
            return;
        }

        $document = $this->normalizer->normalize($entity);

        if ($document !== null) {
            $this->manager->createOrUpdateDocument($document['collection'], $document['document']);
        }
    }
}
