<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Micka17\TypesenseBundle\Attribute\TypesenseField;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use Micka17\TypesenseBundle\Service\TypesenseNormalizer;
use PHPUnit\Framework\TestCase;

// --- Fixtures ---

#[TypesenseIndexable(collection: 'products')]
class NormProduct
{
    public function __construct(public int $id = 1) {}
    public function getId(): int { return $this->id; }

    #[TypesenseField(type: 'string')]
    public string $name = 'Laptop';

    #[TypesenseField(type: 'int32')]
    public int $stock = 100;

    #[TypesenseField(type: 'float')]
    public float $price = 9.99;

    #[TypesenseField(type: 'bool')]
    public bool $available = true;

    #[TypesenseField(type: 'string', optional: true)]
    public ?string $description = null;

    #[TypesenseField(embed: ['from' => ['name'], 'model_config' => ['model_name' => 'ts/e5-small']])]
    public array $embedding = [0.1, 0.2];
}

#[TypesenseIndexable(collection: 'products', normalizerMethod: 'toTypesense')]
class NormProductCustomMethod
{
    public function getId(): int { return 42; }
    public function toTypesense(): array { return ['name' => 'from-method', 'extra' => true]; }
}

#[TypesenseIndexable(collection: 'products')]
class NormProductWithGetter
{
    public function getId(): int { return 5; }

    #[TypesenseField(getter: 'getFormattedName')]
    public string $name = 'raw';

    public function getFormattedName(): string { return 'formatted'; }
}

#[TypesenseIndexable(collection: 'orders')]
class NormOrder
{
    public function __construct(public int $id = 10) {}
    public function getId(): int { return $this->id; }

    #[TypesenseField(reference: 'products.id')]
    public ?NormRelatedProduct $product = null;
}

class NormRelatedProduct
{
    public function getId(): int { return 99; }
}

#[TypesenseIndexable(collection: 'invoices')]
class NormInvoiceWithCollection
{
    public function getId(): int { return 7; }

    #[TypesenseField(type: 'object[]')]
    public mixed $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection([new NormLine(), new NormLine()]);
    }
}

#[TypesenseIndexable(collection: 'lines')]
class NormLine
{
    #[TypesenseField(type: 'string')]
    public string $label = 'item';
    public function getId(): int { return 1; }
}

#[TypesenseIndexable(collection: 'events')]
class NormEventWithDate
{
    public function getId(): int { return 3; }

    #[TypesenseField(type: 'int64')]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('2024-01-15 12:00:00');
    }
}

#[TypesenseIndexable(collection: 'vectors')]
class NormVectorEntity
{
    public function getId(): int { return 2; }

    #[TypesenseField(type: 'float[]', numDim: 3)]
    public array $vector = ['1', '2', '3'];
}

class NormNoAttribute
{
    public string $name = 'test';
}

// --- Tests ---

class TypesenseNormalizerTest extends TestCase
{
    private TypesenseNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new TypesenseNormalizer();
    }

    public function testNormalizeBasicScalars(): void
    {
        $result = $this->normalizer->normalize(new NormProduct());

        $doc = $result['document'];
        $this->assertSame('1', $doc['id']);
        $this->assertSame('Laptop', $doc['name']);
        $this->assertSame(100, $doc['stock']);
        $this->assertSame(9.99, $doc['price']);
        $this->assertTrue($doc['available']);
        $this->assertNull($doc['description']);
    }

    public function testEmbedFieldIsOmittedFromDocument(): void
    {
        $result = $this->normalizer->normalize(new NormProduct());

        $this->assertArrayNotHasKey('embedding', $result['document']);
    }

    public function testCustomNormalizerMethod(): void
    {
        $result = $this->normalizer->normalize(new NormProductCustomMethod());

        $this->assertSame('products', $result['collection']);
        $this->assertSame('from-method', $result['document']['name']);
        $this->assertTrue($result['document']['extra']);
        $this->assertSame('42', $result['document']['id']);
    }

    public function testGetterOverride(): void
    {
        $result = $this->normalizer->normalize(new NormProductWithGetter());

        $this->assertSame('formatted', $result['document']['name']);
    }

    public function testReferenceFieldResolvesToId(): void
    {
        $order = new NormOrder();
        $order->product = new NormRelatedProduct();

        $result = $this->normalizer->normalize($order);

        $this->assertSame('99', $result['document']['product']);
    }

    public function testReferenceNullIsPassedThrough(): void
    {
        $result = $this->normalizer->normalize(new NormOrder());

        $this->assertNull($result['document']['product']);
    }

    public function testCollectionOfObjectsWithTypesenseFields(): void
    {
        $result = $this->normalizer->normalize(new NormInvoiceWithCollection());

        $this->assertIsArray($result['document']['lines']);
        $this->assertCount(2, $result['document']['lines']);
        $this->assertSame('item', $result['document']['lines'][0]['label']);
    }

    public function testDateTimeConvertsToTimestamp(): void
    {
        $entity = new NormEventWithDate();
        $result = $this->normalizer->normalize($entity);

        $this->assertIsInt($result['document']['createdAt']);
        $this->assertSame($entity->createdAt->getTimestamp(), $result['document']['createdAt']);
    }

    public function testFloatArrayCoercedFromStrings(): void
    {
        $result = $this->normalizer->normalize(new NormVectorEntity());

        $this->assertSame([1.0, 2.0, 3.0], $result['document']['vector']);
    }

    public function testEntityWithoutIndexableReturnsNull(): void
    {
        $this->assertNull($this->normalizer->normalize(new NormNoAttribute()));
    }

    public function testEntityWithNullIdReturnsNull(): void
    {
        $entity = new class { public function getId(): ?int { return null; } };

        $this->assertNull($this->normalizer->normalize($entity));
    }

    public function testCollectionKey(): void
    {
        $result = $this->normalizer->normalize(new NormProduct());

        $this->assertSame('products', $result['collection']);
    }

    public function testTypeCoercionInt32(): void
    {
        $entity = new NormProduct();
        $entity->stock = 50;
        $result = $this->normalizer->normalize($entity);

        $this->assertSame(50, $result['document']['stock']);
        $this->assertIsInt($result['document']['stock']);
    }

    public function testTypeCoercionFloat(): void
    {
        $entity = new NormProduct();
        $entity->price = 19.5;
        $result = $this->normalizer->normalize($entity);

        $this->assertIsFloat($result['document']['price']);
    }
}
