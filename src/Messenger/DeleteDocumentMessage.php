<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Messenger;

final class DeleteDocumentMessage
{
    public function __construct(
        public readonly string $collectionName,
        public readonly string $documentId,
    ) {}
}
