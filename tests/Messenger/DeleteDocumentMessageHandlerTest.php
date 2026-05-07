<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Messenger;

use Micka17\TypesenseBundle\Messenger\DeleteDocumentMessage;
use Micka17\TypesenseBundle\Messenger\DeleteDocumentMessageHandler;
use Micka17\TypesenseBundle\Service\TypesenseManager;
use PHPUnit\Framework\TestCase;

class DeleteDocumentMessageHandlerTest extends TestCase
{
    public function testInvokeCallsDeleteDocument(): void
    {
        $manager = $this->createMock(TypesenseManager::class);
        $manager->expects($this->once())
            ->method('deleteDocument')
            ->with('products', '42');

        $handler = new DeleteDocumentMessageHandler($manager);
        ($handler)(new DeleteDocumentMessage('products', '42'));
    }
}
