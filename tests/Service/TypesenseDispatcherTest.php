<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Messenger\DeleteDocumentMessage;
use Micka17\TypesenseBundle\Messenger\IndexDocumentMessage;
use Micka17\TypesenseBundle\Service\TypesenseDispatcher;
use Micka17\TypesenseBundle\Service\TypesenseManager;
use Micka17\TypesenseBundle\Service\TypesenseNormalizer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
class TypesenseDispatcherTest extends TestCase
{
    private TypesenseManager $manager;
    private TypesenseNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->manager    = $this->createMock(TypesenseManager::class);
        $this->normalizer = $this->createMock(TypesenseNormalizer::class);
    }

    private function makeDispatcher(bool $async, ?MessageBusInterface $bus = null): TypesenseDispatcher
    {
        return new TypesenseDispatcher($this->manager, $this->normalizer, $async, $bus);
    }

    // --- Sync mode ---

    public function testSyncDispatchIndexCallsNormalizerAndManager(): void
    {
        $entity = new \stdClass();
        $document = ['collection' => 'products', 'document' => ['id' => '1']];

        $this->normalizer->expects($this->once())->method('normalize')->with($entity)->willReturn($document);
        $this->manager->expects($this->once())->method('createOrUpdateDocument')->with('products', ['id' => '1']);

        $this->makeDispatcher(false)->dispatchIndex($entity);
    }

    public function testSyncDispatchIndexSkipsWhenNormalizerReturnsNull(): void
    {
        $this->normalizer->expects($this->once())->method('normalize')->willReturn(null);
        $this->manager->expects($this->never())->method('createOrUpdateDocument');

        $this->makeDispatcher(false)->dispatchIndex(new \stdClass());
    }

    public function testSyncDispatchDeleteCallsManager(): void
    {
        $this->manager->expects($this->once())->method('deleteDocument')->with('products', '42');

        $this->makeDispatcher(false)->dispatchDelete('products', '42');
    }

    // --- Async mode ---

    public function testAsyncDispatchIndexDispatchesMessage(): void
    {
        $entity = new class {
            public function getId(): string { return '7'; }
        };

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                fn(object $msg) => $msg instanceof IndexDocumentMessage
                    && $msg->entityClass === get_class($entity)
                    && $msg->entityId === '7'
            ))
            ->willReturn(new Envelope(new \stdClass()));

        $this->normalizer->expects($this->never())->method('normalize');

        $this->makeDispatcher(true, $bus)->dispatchIndex($entity);
    }

    public function testAsyncDispatchIndexSkipsWhenNoGetId(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $this->makeDispatcher(true, $bus)->dispatchIndex(new \stdClass());
    }

    public function testAsyncDispatchDeleteDispatchesMessage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                fn(object $msg) => $msg instanceof DeleteDocumentMessage
                    && $msg->collectionName === 'products'
                    && $msg->documentId === '42'
            ))
            ->willReturn(new Envelope(new \stdClass()));

        $this->makeDispatcher(true, $bus)->dispatchDelete('products', '42');
    }

    // --- No bus in async mode ---

    public function testAsyncWithoutBusThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/symfony\/messenger/');

        $entity = new class {
            public function getId(): string { return '1'; }
        };

        $this->makeDispatcher(true, null)->dispatchIndex($entity);
    }

    public function testAsyncDeleteWithoutBusThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->makeDispatcher(true, null)->dispatchDelete('col', '1');
    }
}
