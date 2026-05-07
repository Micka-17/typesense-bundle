<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Messenger;

use Micka17\TypesenseBundle\Service\TypesenseManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteDocumentMessageHandler
{
    public function __construct(
        private readonly TypesenseManager $manager,
    ) {}

    public function __invoke(DeleteDocumentMessage $message): void
    {
        $this->manager->deleteDocument($message->collectionName, $message->documentId);
    }
}
