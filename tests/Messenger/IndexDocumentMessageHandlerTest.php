<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Micka17\TypesenseBundle\Messenger\IndexDocumentMessage;
use Micka17\TypesenseBundle\Messenger\IndexDocumentMessageHandler;
use Micka17\TypesenseBundle\Service\TypesenseManager;
use Micka17\TypesenseBundle\Service\TypesenseNormalizer;
use PHPUnit\Framework\TestCase;

class IndexDocumentMessageHandlerTest extends TestCase
{
    private EntityManagerInterface $em;
    private TypesenseNormalizer $normalizer;
    private TypesenseManager $manager;
    private IndexDocumentMessageHandler $handler;

    protected function setUp(): void
    {
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->normalizer = $this->createMock(TypesenseNormalizer::class);
        $this->manager    = $this->createMock(TypesenseManager::class);
        $this->handler    = new IndexDocumentMessageHandler($this->em, $this->normalizer, $this->manager);
    }

    public function testInvokeFetchesAndIndexesEntity(): void
    {
        $entity = new \stdClass();

        $this->em->expects($this->once())
            ->method('find')
            ->with('App\Entity\Product', '42')
            ->willReturn($entity);

        $this->normalizer->expects($this->once())
            ->method('normalize')
            ->with($entity)
            ->willReturn(['collection' => 'products', 'document' => ['id' => '42', 'name' => 'Widget']]);

        $this->manager->expects($this->once())
            ->method('createOrUpdateDocument')
            ->with('products', ['id' => '42', 'name' => 'Widget']);

        ($this->handler)(new IndexDocumentMessage('App\Entity\Product', '42'));
    }

    public function testInvokeSkipsWhenEntityNotFound(): void
    {
        $this->em->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->normalizer->expects($this->never())->method('normalize');
        $this->manager->expects($this->never())->method('createOrUpdateDocument');

        ($this->handler)(new IndexDocumentMessage('App\Entity\Product', '99'));
    }

    public function testInvokeSkipsWhenNormalizerReturnsNull(): void
    {
        $entity = new \stdClass();

        $this->em->expects($this->once())->method('find')->willReturn($entity);
        $this->normalizer->expects($this->once())->method('normalize')->willReturn(null);
        $this->manager->expects($this->never())->method('createOrUpdateDocument');

        ($this->handler)(new IndexDocumentMessage('App\Entity\Product', '1'));
    }
}
