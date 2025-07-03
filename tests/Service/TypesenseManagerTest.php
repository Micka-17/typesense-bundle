<?php

namespace Micka17\TypesenseBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Micka17\TypesenseBundle\Service\SchemaGenerator;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use Micka17\TypesenseBundle\Service\TypesenseErrorTracker;
use Micka17\TypesenseBundle\Service\TypesenseManager;
use Micka17\TypesenseBundle\Service\TypesenseNormalizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Typesense\Exceptions\ObjectNotFound;

class TypesenseManagerTest extends TestCase
{
    private MockObject|TypesenseClient $clientMock;
    private MockObject|EntityManagerInterface $emMock;
    private MockObject|SchemaGenerator $schemaGeneratorMock;
    private MockObject|TypesenseNormalizer $normalizerMock;
    private MockObject|TypesenseErrorTracker $errorTrackerMock;
    private MockObject|SymfonyStyle $ioMock;
    private TypesenseManager $manager;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(TypesenseClient::class);
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->schemaGeneratorMock = $this->createMock(SchemaGenerator::class);
        $this->normalizerMock = $this->createMock(TypesenseNormalizer::class);
        $this->errorTrackerMock = $this->createMock(TypesenseErrorTracker::class);
        $this->ioMock = $this->createMock(SymfonyStyle::class);

        $this->manager = new TypesenseManager(
            $this->clientMock,
            $this->emMock,
            $this->schemaGeneratorMock,
            $this->normalizerMock,
            $this->errorTrackerMock
        );
    }

    public function testCreateCollectionForEntitySuccess(): void
    {
        $schema = ['name' => 'my_collection'];
        $this->schemaGeneratorMock->expects($this->once())
            ->method('generate')->with('MyEntity')->willReturn($schema);

        $this->clientMock->expects($this->once())->method('createCollection')->with($schema);

        $this->ioMock->expects($this->once())->method('info')->with("Création de la collection 'my_collection'...");
        $this->ioMock->expects($this->once())->method('success')->with("Collection 'my_collection' créée avec succès.");

        $this->manager->createCollectionForEntity('MyEntity', $this->ioMock);
    }

    public function testCreateCollectionForEntityHandlesException(): void
    {
        $schema = ['name' => 'my_collection'];
        $exception = new \Exception('Creation failed');

        $this->schemaGeneratorMock->method('generate')->willReturn($schema);
        $this->clientMock->method('createCollection')->willThrowException($exception);

        $this->errorTrackerMock->expects($this->once())
            ->method('trackError')
            ->with("Erreur lors de l'création pour la collection 'my_collection'", ['collection' => 'my_collection'], $exception);

        $this->ioMock->expects($this->once())->method('error')->with($this->stringContains('Creation failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Creation failed');

        $this->manager->createCollectionForEntity('MyEntity', $this->ioMock);
    }

    public function testDeleteCollectionForEntitySuccess(): void
    {
        $schema = ['name' => 'my_collection'];
        $this->schemaGeneratorMock->expects($this->once())
            ->method('generate')->with('MyEntity')->willReturn($schema);

        $this->clientMock->expects($this->once())->method('deleteCollection')->with('my_collection');
        $this->ioMock->expects($this->once())->method('success')->with("Collection 'my_collection' supprimée avec succès.");

        $this->manager->deleteCollectionForEntity('MyEntity', $this->ioMock);
    }

    public function testDeleteCollectionForEntityHandlesObjectNotFound(): void
    {
        $schema = ['name' => 'my_collection'];
        $this->schemaGeneratorMock->method('generate')->willReturn($schema);
        $this->clientMock->method('deleteCollection')->willThrowException(new ObjectNotFound());

        $this->ioMock->expects($this->once())->method('warning')->with("La collection 'my_collection' n'existait pas. Aucune action n'a été effectuée.");
        $this->errorTrackerMock->expects($this->never())->method('trackError');

        $this->manager->deleteCollectionForEntity('MyEntity', $this->ioMock);
    }

    public function testRecreateCollectionForEntity(): void
    {
        $manager = $this->getMockBuilder(TypesenseManager::class)
            ->onlyMethods(['deleteCollectionForEntity', 'createCollectionForEntity'])
            ->setConstructorArgs([$this->clientMock, $this->emMock, $this->schemaGeneratorMock, $this->normalizerMock, $this->errorTrackerMock])
            ->getMock();

        $this->ioMock->expects($this->once())->method('section')->with("Recréation de la collection pour l'entité 'MyEntity'");

        $manager->expects($this->once())->method('deleteCollectionForEntity')->with('MyEntity', $this->ioMock);
        $manager->expects($this->once())->method('createCollectionForEntity')->with('MyEntity', $this->ioMock);

        $this->ioMock->expects($this->once())->method('success')->with("Collection recréée avec succès.");

        $manager->recreateCollectionForEntity('MyEntity', $this->ioMock);
    }

    public function testReindexEntityCollectionSuccess(): void
    {
        $entity = new \stdClass();
        $schema = ['name' => 'my_collection'];
        $entities = [$entity];

        $this->schemaGeneratorMock->method('generate')->willReturn($schema);

        $repositoryMock = $this->createMock(EntityRepository::class);
        $repositoryMock->method('findAll')->willReturn($entities);
        $this->emMock->method('getRepository')->with('stdClass')->willReturn($repositoryMock);

        $this->normalizerMock->method('normalize')->with($entity)->willReturn(['document' => ['id' => 1, 'field' => 'value']]);
        $this->clientMock->method('importDocuments')
            ->with('my_collection', [['id' => 1, 'field' => 'value']])
            ->willReturn([['success' => true]]);

        $this->ioMock->expects($this->once())->method('success')->with("1 documents de 'stdClass' ont été indexés avec succès.");

        $count = $this->manager->reindexEntityCollection('stdClass', $this->ioMock);
        $this->assertSame(1, $count);
    }

    public function testReindexEntityCollectionWithImportFailures(): void
    {
        $entities = [new \stdClass(), new \stdClass()];
        $schema = ['name' => 'my_collection'];
        $importResults = [
            ['success' => true],
            ['success' => false, 'error' => 'Invalid field type.'],
        ];

        $this->schemaGeneratorMock->method('generate')->willReturn($schema);

        $repositoryMock = $this->createMock(EntityRepository::class);
        $repositoryMock->method('findAll')->willReturn($entities);
        $this->emMock->method('getRepository')->willReturn($repositoryMock);

        $this->normalizerMock->method('normalize')->willReturn(['document' => ['id' => 1]]);
        $this->clientMock->method('importDocuments')->willReturn($importResults);

        $this->ioMock->expects($this->once())->method('warning')->with("L'importation est terminée, mais 1 documents sur 2 ont été rejetés par Typesense.");
        $this->ioMock->expects($this->once())->method('note')->with("Première erreur rapportée : Invalid field type.");

        $count = $this->manager->reindexEntityCollection('stdClass', $this->ioMock);
        $this->assertSame(1, $count);
    }

    public function testReindexEntityCollectionHandlesNoRepositoryDocuments(): void
    {
        $schema = ['name' => 'my_collection'];
        $this->schemaGeneratorMock->method('generate')->willReturn($schema);

        $repositoryMock = $this->createMock(EntityRepository::class);
        $repositoryMock->method('findAll')->willReturn([]);
        $this->emMock->method('getRepository')->willReturn($repositoryMock);

        $this->ioMock->expects($this->once())->method('warning')->with("Aucun document trouvé pour l'entité 'stdClass'.");
        $this->clientMock->expects($this->never())->method('importDocuments');

        $count = $this->manager->reindexEntityCollection('stdClass', $this->ioMock);
        $this->assertSame(0, $count);
    }

    public function testReindexEntityCollectionHandlesNormalizationReturningNoDocuments(): void
    {
        $schema = ['name' => 'my_collection'];
        $this->schemaGeneratorMock->method('generate')->willReturn($schema);

        $repositoryMock = $this->createMock(EntityRepository::class);
        $repositoryMock->method('findAll')->willReturn([new \stdClass()]);
        $this->emMock->method('getRepository')->willReturn($repositoryMock);

        $this->normalizerMock->method('normalize')->willReturn(null);

        $this->ioMock->expects($this->once())->method('warning')->with("La normalisation n'a produit aucun document à indexer.");
        $this->clientMock->expects($this->never())->method('importDocuments');

        $count = $this->manager->reindexEntityCollection('stdClass', $this->ioMock);
        $this->assertSame(0, $count);
    }

    public function testReindexEntityCollectionHandlesObjectNotFoundException(): void
    {
        $schema = ['name' => 'my_collection'];
        $this->schemaGeneratorMock->method('generate')->willReturn($schema);

        $repositoryMock = $this->createMock(EntityRepository::class);
        $repositoryMock->method('findAll')->willReturn([new \stdClass()]);
        $this->emMock->method('getRepository')->willReturn($repositoryMock);

        $this->normalizerMock->method('normalize')->willReturn(['document' => ['id' => 1]]);
        $exception = new ObjectNotFound();
        $this->clientMock->method('importDocuments')->willThrowException($exception);

        $this->errorTrackerMock->expects($this->once())
            ->method('trackError')
            ->with("Tentative d'indexation dans une collection inexistante", ['collection' => 'my_collection'], $exception);

        $this->ioMock->expects($this->once())->method('error')->with("La collection 'my_collection' n'existe pas. Veuillez la créer avant de lancer une indexation.");
        $this->ioMock->expects($this->once())->method('note')->with("Astuce : utilisez la commande 'micka17:typesense:manage recreate \"stdClass\"' pour la créer.");

        $count = $this->manager->reindexEntityCollection('stdClass', $this->ioMock);
        $this->assertSame(0, $count);
    }

    /**
     * @dataProvider provideProxyMethods
     */
    public function testProxyMethods(string $methodName, array $args): void
    {
        $this->clientMock->expects($this->once())
            ->method($methodName)
            ->with(...$args)
            ->willReturn(['success' => true]);

        $result = $this->manager->$methodName(...$args);
        $this->assertEquals(['success' => true], $result);
    }

    public static function provideProxyMethods(): iterable
    {
        yield 'createCollection' => ['createCollection', [['name' => 'test']]];
        yield 'deleteCollection' => ['deleteCollection', ['test']];
        yield 'createOrUpdateDocument' => ['createOrUpdateDocument', ['test_collection', ['id' => '1']]];
        yield 'deleteDocument' => ['deleteDocument', ['test_collection', '1']];
    }
}