<?php

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Service\TypesenseClient;
use PHPUnit\Framework\TestCase;
use Typesense\Client;
use Typesense\Collection;
use Typesense\Collections;
use Typesense\Exceptions\ObjectNotFound;

class TypesenseClientTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject|Client $mockClient;
    private \PHPUnit\Framework\MockObject\MockObject|Collections $mockCollections;
    private TestableTypesenseClient $typesenseClient;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(Client::class);
        $this->mockCollections = $this->createMock(Collections::class);

        $this->mockClient->collections = $this->mockCollections;

        $this->typesenseClient = new TestableTypesenseClient($this->mockClient);
    }

    public function testCreateCollectionSuccess(): void
    {
        $schema = ['name' => 'books', 'fields' => [['name' => 'title', 'type' => 'string']]];
        $expected = ['name' => 'books'];

        $this->mockCollections
            ->expects($this->once())
            ->method('create')
            ->with($schema)
            ->willReturn($expected);

        $result = $this->typesenseClient->createCollection($schema);
        $this->assertEquals($expected, $result);
    }

    public function testDeleteCollectionSuccess(): void
    {
        $collectionName = 'books';

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock->expects($this->once())
            ->method('delete')
            ->willReturn(['success' => true]);

        $this->mockCollections
            ->method('offsetGet')
            ->with($collectionName)
            ->willReturn($collectionMock);

        $result = $this->typesenseClient->deleteCollection($collectionName);
        $this->assertEquals(['success' => true], $result);
    }

    public function testDeleteCollectionNotFound(): void
    {
        $collectionName = 'missing';

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock->expects($this->once())
            ->method('delete')
            ->willThrowException(new ObjectNotFound());

        $this->mockCollections
            ->method('offsetGet')
            ->with($collectionName)
            ->willReturn($collectionMock);

        $result = $this->typesenseClient->deleteCollection($collectionName);
        $this->assertEquals(['success' => true], $result);
    }

    public function testImportDocuments(): void
    {
        $collectionName = 'books';
        $documents = array_map(fn($i) => ['id' => (string)$i, 'title' => "Book $i"], range(1, 3));
        $expectedResult = [['success' => true], ['success' => true], ['success' => true]];

        $mockDocuments = $this->createMock(\Typesense\Documents::class);
        $mockDocuments->expects($this->once())
            ->method('import')
            ->with($documents, ['action' => 'upsert'])
            ->willReturn($expectedResult);

        $collectionMock = $this->createMock(\Typesense\Collection::class);
        $collectionMock->documents = $mockDocuments;

        $this->mockCollections
            ->method('offsetGet')
            ->with($collectionName)
            ->willReturn($collectionMock);

        $result = $this->typesenseClient->importDocuments($collectionName, $documents);
        $this->assertEquals($expectedResult, $result);
    }

    public function testDocumentExistsTrue(): void
    {
        $collectionName = 'books';
        $documentId = '123';

        $mockDocument = $this->createMock(\Typesense\Document::class);
        $mockDocument->expects($this->once())
            ->method('retrieve')
            ->willReturn(['id' => $documentId]);

        $mockDocuments = $this->createMock(\Typesense\Documents::class);
        $mockDocuments->method('offsetGet')
            ->with($documentId)
            ->willReturn($mockDocument);

        $collectionMock = $this->createMock(\Typesense\Collection::class);
        $collectionMock->documents = $mockDocuments;

        $this->mockCollections
            ->method('offsetGet')
            ->with($collectionName)
            ->willReturn($collectionMock);

        $this->assertTrue($this->typesenseClient->documentExists($collectionName, $documentId));
    }

    public function testDocumentExistsFalse(): void
    {
        $collectionName = 'books';
        $documentId = 'not_found';

        $mockDocument = $this->createMock(\Typesense\Document::class);
        $mockDocument->expects($this->once())
            ->method('retrieve')
            ->willThrowException(new ObjectNotFound());

        $mockDocuments = $this->createMock(\Typesense\Documents::class);
        $mockDocuments->method('offsetGet')
            ->with($documentId)
            ->willReturn($mockDocument);

        $collectionMock = $this->createMock(\Typesense\Collection::class);
        $collectionMock->documents = $mockDocuments;

        $this->mockCollections
            ->method('offsetGet')
            ->with($collectionName)
            ->willReturn($collectionMock);

        $this->assertFalse($this->typesenseClient->documentExists($collectionName, $documentId));
    }

    public function testSearch(): void
    {
        $collectionName = 'books';
        $searchParams = ['q' => 'PHP', 'query_by' => 'title'];
        $expectedResult = ['hits' => [['document' => ['title' => 'Learn PHP']]]];

        $mockDocuments = $this->createMock(\Typesense\Documents::class);
        $mockDocuments->expects($this->once())
            ->method('search')
            ->with($searchParams)
            ->willReturn($expectedResult);

        $collectionMock = $this->createMock(\Typesense\Collection::class);
        $collectionMock->documents = $mockDocuments;

        $this->mockCollections
            ->method('offsetGet')
            ->with($collectionName)
            ->willReturn($collectionMock);

        $result = $this->typesenseClient->search($collectionName, $searchParams);
        $this->assertEquals($expectedResult, $result);
    }

    public function testCreateOrUpdateDocument(): void
    {
        $collectionName = 'books';
        $document = ['id' => '123', 'title' => 'New Book'];
        $expectedResult = ['id' => '123', 'title' => 'New Book'];

        $mockDocuments = $this->createMock(\Typesense\Documents::class);
        $mockDocuments->expects($this->once())
            ->method('upsert')
            ->with($document)
            ->willReturn($expectedResult);

        $collectionMock = $this->createMock(\Typesense\Collection::class);
        $collectionMock->documents = $mockDocuments;

        $this->mockCollections
            ->method('offsetGet')
            ->with($collectionName)
            ->willReturn($collectionMock);

        $result = $this->typesenseClient->createOrUpdateDocument($collectionName, $document);
        $this->assertEquals($expectedResult, $result);
    }

    public function testDeleteDocument(): void
    {
        $collectionName = 'books';
        $documentId = '123';
        $expectedResult = ['success' => true];

        $mockDocument = $this->createMock(\Typesense\Document::class);
        $mockDocument->expects($this->once())
            ->method('delete')
            ->willReturn($expectedResult);

        $mockDocuments = $this->createMock(\Typesense\Documents::class);
        $mockDocuments->method('offsetGet')
            ->with($documentId)
            ->willReturn($mockDocument);

        $collectionMock = $this->createMock(\Typesense\Collection::class);
        $collectionMock->documents = $mockDocuments;

        $this->mockCollections
            ->method('offsetGet')
            ->with($collectionName)
            ->willReturn($collectionMock);

        $result = $this->typesenseClient->deleteDocument($collectionName, $documentId);
        $this->assertEquals($expectedResult, $result);
    }

}

class TestableTypesenseClient extends TypesenseClient
{
    public function __construct(Client $mockClient)
    {
        $refClass = new \ReflectionClass(TypesenseClient::class);
        $property = $refClass->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this, $mockClient);
    }
}
