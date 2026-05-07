<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Messenger;

final class IndexDocumentMessage
{
    /**
     * @param class-string $entityClass
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly string $entityId,
    ) {}
}
