<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Service\SchemaGenerator;
use Micka17\TypesenseBundle\Service\SchemaDiffService;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use PHPUnit\Framework\TestCase;
use Typesense\Exceptions\ObjectNotFound;

class SchemaDiffServiceTest extends TestCase
{
    private TypesenseClient $client;
    private SchemaGenerator $generator;
    private SchemaDiffService $service;

    protected function setUp(): void
    {
        $this->client    = $this->createMock(TypesenseClient::class);
        $this->generator = $this->createMock(SchemaGenerator::class);
        $this->service   = new SchemaDiffService($this->client, $this->generator);
    }

    public function testDiffWhenCollectionDoesNotExist(): void
    {
        $this->generator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'name'   => 'books',
                'fields' => [
                    ['name' => 'title', 'type' => 'string', 'optional' => false],
                ],
            ]);

        $this->client->expects($this->once())
            ->method('retrieveCollection')
            ->with('books')
            ->willThrowException(new ObjectNotFound());

        $diff = $this->service->diff('App\Entity\Book');

        $this->assertSame('books', $diff->collectionName);
        $this->assertCount(1, $diff->fieldsToAdd);
        $this->assertSame([], $diff->fieldsToDrop);
        $this->assertSame([], $diff->fieldConflicts);
        $this->assertFalse($diff->metadataChanged);
        $this->assertTrue($diff->hasChanges());
        $this->assertFalse($diff->requiresRecreation());
    }

    public function testDiffNoChanges(): void
    {
        $fields = [['name' => 'title', 'type' => 'string', 'optional' => false]];

        $this->generator->expects($this->once())
            ->method('generate')
            ->willReturn(['name' => 'books', 'fields' => $fields]);

        $this->client->expects($this->once())
            ->method('retrieveCollection')
            ->with('books')
            ->willReturn(['name' => 'books', 'fields' => array_merge($fields, [['name' => 'id', 'type' => 'string']])]);

        $diff = $this->service->diff('App\Entity\Book');

        $this->assertFalse($diff->hasChanges());
    }

    public function testDiffDetectsFieldToAdd(): void
    {
        $this->generator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'name'   => 'books',
                'fields' => [
                    ['name' => 'title', 'type' => 'string', 'optional' => false],
                    ['name' => 'author', 'type' => 'string', 'optional' => true],
                ],
            ]);

        $this->client->expects($this->once())
            ->method('retrieveCollection')
            ->willReturn([
                'name'   => 'books',
                'fields' => [
                    ['name' => 'title', 'type' => 'string'],
                    ['name' => 'id', 'type' => 'string'],
                ],
            ]);

        $diff = $this->service->diff('App\Entity\Book');

        $this->assertCount(1, $diff->fieldsToAdd);
        $this->assertSame('author', $diff->fieldsToAdd[0]['name']);
        $this->assertSame([], $diff->fieldsToDrop);
        $this->assertFalse($diff->requiresRecreation());
    }

    public function testDiffDetectsFieldToDrop(): void
    {
        $this->generator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'name'   => 'books',
                'fields' => [
                    ['name' => 'title', 'type' => 'string', 'optional' => false],
                ],
            ]);

        $this->client->expects($this->once())
            ->method('retrieveCollection')
            ->willReturn([
                'name'   => 'books',
                'fields' => [
                    ['name' => 'title', 'type' => 'string'],
                    ['name' => 'deprecated_field', 'type' => 'string'],
                    ['name' => 'id', 'type' => 'string'],
                ],
            ]);

        $diff = $this->service->diff('App\Entity\Book');

        $this->assertSame(['deprecated_field'], $diff->fieldsToDrop);
        $this->assertSame([], $diff->fieldsToAdd);
        $this->assertFalse($diff->requiresRecreation());
    }

    public function testDiffDetectsFieldTypeConflict(): void
    {
        $this->generator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'name'   => 'books',
                'fields' => [
                    ['name' => 'rating', 'type' => 'float', 'optional' => false],
                ],
            ]);

        $this->client->expects($this->once())
            ->method('retrieveCollection')
            ->willReturn([
                'name'   => 'books',
                'fields' => [
                    ['name' => 'rating', 'type' => 'int32'],
                    ['name' => 'id', 'type' => 'string'],
                ],
            ]);

        $diff = $this->service->diff('App\Entity\Book');

        $this->assertCount(1, $diff->fieldConflicts);
        $this->assertSame('rating', $diff->fieldConflicts[0]['name']);
        $this->assertSame('int32', $diff->fieldConflicts[0]['live_type']);
        $this->assertSame('float', $diff->fieldConflicts[0]['generated_type']);
        $this->assertTrue($diff->requiresRecreation());
    }

    public function testDiffDetectsMetadataChange(): void
    {
        $this->generator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'name'                  => 'books',
                'fields'                => [['name' => 'title', 'type' => 'string', 'optional' => false]],
                'enable_nested_fields'  => true,
            ]);

        $this->client->expects($this->once())
            ->method('retrieveCollection')
            ->willReturn([
                'name'   => 'books',
                'fields' => [['name' => 'title', 'type' => 'string']],
                // enable_nested_fields absent in live
            ]);

        $diff = $this->service->diff('App\Entity\Book');

        $this->assertTrue($diff->metadataChanged);
        $this->assertTrue($diff->requiresRecreation());
    }

    public function testApplyDiffCallsUpdateCollection(): void
    {
        $this->generator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'name'   => 'books',
                'fields' => [
                    ['name' => 'title', 'type' => 'string', 'optional' => false],
                    ['name' => 'author', 'type' => 'string', 'optional' => true],
                ],
            ]);

        $this->client->expects($this->once())
            ->method('retrieveCollection')
            ->willReturn([
                'name'   => 'books',
                'fields' => [
                    ['name' => 'title', 'type' => 'string'],
                    ['name' => 'old_field', 'type' => 'string'],
                    ['name' => 'id', 'type' => 'string'],
                ],
            ]);

        $this->client->expects($this->once())
            ->method('updateCollection')
            ->with('books', $this->callback(function (array $fields): bool {
                $names = array_column($fields, 'name');
                return in_array('author', $names, true) && in_array('old_field', $names, true);
            }))
            ->willReturn(['name' => 'books']);

        $diff = $this->service->diff('App\Entity\Book');
        $this->service->applyDiff($diff);
    }

    public function testApplyDiffSkipsWhenRequiresRecreation(): void
    {
        $this->generator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'name'   => 'books',
                'fields' => [['name' => 'rating', 'type' => 'float', 'optional' => false]],
            ]);

        $this->client->expects($this->once())
            ->method('retrieveCollection')
            ->willReturn([
                'name'   => 'books',
                'fields' => [['name' => 'rating', 'type' => 'int32']],
            ]);

        $this->client->expects($this->never())
            ->method('updateCollection');

        $diff = $this->service->diff('App\Entity\Book');
        $this->service->applyDiff($diff);
    }

    public function testApplyDiffSkipsWhenNoChanges(): void
    {
        $this->generator->expects($this->once())
            ->method('generate')
            ->willReturn([
                'name'   => 'books',
                'fields' => [['name' => 'title', 'type' => 'string', 'optional' => false]],
            ]);

        $this->client->expects($this->once())
            ->method('retrieveCollection')
            ->willReturn([
                'name'   => 'books',
                'fields' => [
                    ['name' => 'title', 'type' => 'string'],
                    ['name' => 'id', 'type' => 'string'],
                ],
            ]);

        $this->client->expects($this->never())
            ->method('updateCollection');

        $diff = $this->service->diff('App\Entity\Book');
        $this->service->applyDiff($diff);
    }
}
