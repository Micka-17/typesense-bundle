<?php

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Service\TypesenseClient;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Typesense\Alias;
use Typesense\Aliases;
use Typesense\Key;
use Typesense\Keys;
use Typesense\Client;
use Typesense\Collection;
use Typesense\Collections;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\MultiSearch;

#[AllowMockObjectsWithoutExpectations]
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

    public function testUpdateCollection(): void
    {
        $fields = [
            ['name' => 'new_field', 'type' => 'string'],
            ['name' => 'old_field', 'drop' => true],
        ];
        $expected = ['name' => 'books', 'fields' => $fields];

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock->expects($this->once())
            ->method('update')
            ->with(['fields' => $fields])
            ->willReturn($expected);

        $this->mockCollections
            ->expects($this->once())
            ->method('offsetGet')
            ->with('books')
            ->willReturn($collectionMock);

        $result = $this->typesenseClient->updateCollection('books', $fields);
        $this->assertEquals($expected, $result);
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
            ->expects($this->once())
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
            ->expects($this->once())
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
            ->expects($this->once())
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
        $mockDocuments->expects($this->once())->method('offsetGet')
            ->with($documentId)
            ->willReturn($mockDocument);

        $collectionMock = $this->createMock(\Typesense\Collection::class);
        $collectionMock->documents = $mockDocuments;

        $this->mockCollections
            ->expects($this->once())
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
        $mockDocuments->expects($this->once())->method('offsetGet')
            ->with($documentId)
            ->willReturn($mockDocument);

        $collectionMock = $this->createMock(\Typesense\Collection::class);
        $collectionMock->documents = $mockDocuments;

        $this->mockCollections
            ->expects($this->once())
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
            ->expects($this->once())
            ->method('offsetGet')
            ->with($collectionName)
            ->willReturn($collectionMock);

        $result = $this->typesenseClient->search($collectionName, $searchParams);
        $this->assertEquals($expectedResult, $result);
    }

    public function testMultiSearch(): void
    {
        $searchRequests = ['searches' => [['collection' => 'books', 'q' => 'PHP']]];
        $expectedResult = ['results' => [['hits' => []]]];

        $mockMultiSearch = $this->createMock(MultiSearch::class);
        $mockMultiSearch->expects($this->once())
            ->method('perform')
            ->with($searchRequests, ['union' => true])
            ->willReturn($expectedResult);

        $this->mockClient->multiSearch = $mockMultiSearch;

        $this->assertSame($expectedResult, $this->typesenseClient->multiSearch($searchRequests, ['union' => true]));
    }

    public function testGetOperationsReturnsNativeClient(): void
    {
        $this->assertSame($this->mockClient, $this->typesenseClient->getClient());
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
            ->expects($this->once())
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
        $mockDocuments->expects($this->once())->method('offsetGet')
            ->with($documentId)
            ->willReturn($mockDocument);

        $collectionMock = $this->createMock(\Typesense\Collection::class);
        $collectionMock->documents = $mockDocuments;

        $this->mockCollections
            ->expects($this->once())
            ->method('offsetGet')
            ->with($collectionName)
            ->willReturn($collectionMock);

        $result = $this->typesenseClient->deleteDocument($collectionName, $documentId);
        $this->assertEquals($expectedResult, $result);
    }

    public function testCreateKey(): void
    {
        $config   = ['description' => 'Search only', 'actions' => ['documents:search'], 'collections' => ['products']];
        $expected = ['id' => 1, 'value' => 'secretkey'];

        $mockKeys = $this->createMock(Keys::class);
        $mockKeys->expects($this->once())->method('create')->with($config)->willReturn($expected);
        $this->mockClient->keys = $mockKeys;

        $result = $this->typesenseClient->createKey($config);
        $this->assertEquals($expected, $result);
    }

    public function testListKeys(): void
    {
        $expected = ['keys' => [['id' => 1]]];

        $mockKeys = $this->createMock(Keys::class);
        $mockKeys->expects($this->once())->method('retrieve')->willReturn($expected);
        $this->mockClient->keys = $mockKeys;

        $this->assertEquals($expected, $this->typesenseClient->listKeys());
    }

    public function testRetrieveKey(): void
    {
        $expected = ['id' => 42, 'description' => 'Read only'];

        $mockKey = $this->createMock(Key::class);
        $mockKey->expects($this->once())->method('retrieve')->willReturn($expected);

        $mockKeys = $this->createMock(Keys::class);
        $mockKeys->expects($this->once())->method('offsetGet')->with('42')->willReturn($mockKey);
        $this->mockClient->keys = $mockKeys;

        $this->assertEquals($expected, $this->typesenseClient->retrieveKey(42));
    }

    public function testDeleteKey(): void
    {
        $expected = ['id' => 42];

        $mockKey = $this->createMock(Key::class);
        $mockKey->expects($this->once())->method('delete')->willReturn($expected);

        $mockKeys = $this->createMock(Keys::class);
        $mockKeys->expects($this->once())->method('offsetGet')->with('42')->willReturn($mockKey);
        $this->mockClient->keys = $mockKeys;

        $this->assertEquals($expected, $this->typesenseClient->deleteKey(42));
    }

    public function testUpdateConfig(): void
    {
        // Build a real Client with a mock ApiCall via reflection
        $realClient = $this->typesenseClient->getClient();

        $mockApiCall = $this->createMock(\Typesense\ApiCall::class);
        $mockApiCall->expects($this->once())
            ->method('post')
            ->with('/config', ['cache-num-entries' => 1000])
            ->willReturn(['ok' => true]);

        $reflection = new \ReflectionProperty(Client::class, 'apiCall');
        $reflection->setValue($realClient, $mockApiCall);

        $result = $this->typesenseClient->updateConfig(['cache-num-entries' => 1000]);
        $this->assertEquals(['ok' => true], $result);
    }

    public function testExportDocuments(): void
    {
        $jsonl = '{"id":"1","name":"laptop"}' . "\n" . '{"id":"2","name":"phone"}';

        $mockDocuments = $this->createMock(\Typesense\Documents::class);
        $mockDocuments->expects($this->once())
            ->method('export')
            ->with(['filter_by' => 'stock:>0'])
            ->willReturn($jsonl);

        $collectionMock = $this->createMock(\Typesense\Collection::class);
        $collectionMock->documents = $mockDocuments;

        $this->mockCollections
            ->expects($this->once())
            ->method('offsetGet')
            ->with('products')
            ->willReturn($collectionMock);

        $result = $this->typesenseClient->exportDocuments('products', ['filter_by' => 'stock:>0']);
        $this->assertSame($jsonl, $result);
    }

    public function testUpsertAlias(): void
    {
        $expected = ['name' => 'products', 'collection_name' => 'products_v2'];

        $mockAliases = $this->createMock(Aliases::class);
        $mockAliases->expects($this->once())
            ->method('upsert')
            ->with('products', ['collection_name' => 'products_v2'])
            ->willReturn($expected);

        $this->mockClient->aliases = $mockAliases;

        $result = $this->typesenseClient->upsertAlias('products', 'products_v2');
        $this->assertEquals($expected, $result);
    }

    public function testListAliases(): void
    {
        $expected = ['aliases' => [['name' => 'products', 'collection_name' => 'products_v1']]];

        $mockAliases = $this->createMock(Aliases::class);
        $mockAliases->expects($this->once())->method('retrieve')->willReturn($expected);

        $this->mockClient->aliases = $mockAliases;

        $result = $this->typesenseClient->listAliases();
        $this->assertEquals($expected, $result);
    }

    public function testRetrieveAlias(): void
    {
        $expected = ['name' => 'products', 'collection_name' => 'products_v1'];

        $mockAlias = $this->createMock(Alias::class);
        $mockAlias->expects($this->once())->method('retrieve')->willReturn($expected);

        $mockAliases = $this->createMock(Aliases::class);
        $mockAliases->expects($this->once())->method('offsetGet')->with('products')->willReturn($mockAlias);

        $this->mockClient->aliases = $mockAliases;

        $result = $this->typesenseClient->retrieveAlias('products');
        $this->assertEquals($expected, $result);
    }

    public function testDeleteAlias(): void
    {
        $expected = ['name' => 'products'];

        $mockAlias = $this->createMock(Alias::class);
        $mockAlias->expects($this->once())->method('delete')->willReturn($expected);

        $mockAliases = $this->createMock(Aliases::class);
        $mockAliases->expects($this->once())->method('offsetGet')->with('products')->willReturn($mockAlias);

        $this->mockClient->aliases = $mockAliases;

        $result = $this->typesenseClient->deleteAlias('products');
        $this->assertEquals($expected, $result);
    }

}

class TestableTypesenseClient extends TypesenseClient
{
    public function __construct(Client $mockClient)
    {
        $refClass = new \ReflectionClass(TypesenseClient::class);
        $property = $refClass->getProperty('client');
        $property->setValue($this, $mockClient);
    }
}
