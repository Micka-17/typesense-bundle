<?php

namespace Micka17\TypesenseBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Micka17\TypesenseBundle\Service\SchemaGenerator;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use Micka17\TypesenseBundle\Service\TypesenseErrorTracker;
use Micka17\TypesenseBundle\Service\TypesenseManager;
use Micka17\TypesenseBundle\Service\TypesenseNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Typesense\Exceptions\ObjectNotFound;

class TypesenseManagerTest extends TestCase
{
//     private $client;
//     private $em;
//     private $schemaGenerator;
//     private $normalizer;
//     private $errorTracker;
//     private $io;
//     private $manager;

//     protected function setUp(): void
//     {
//         $this->client = $this->createMock(TypesenseClient::class);
//         $this->em = $this->createMock(EntityManagerInterface::class);
//         $this->schemaGenerator = $this->createMock(SchemaGenerator::class);
//         $this->normalizer = $this->createMock(TypesenseNormalizer::class);
//         $this->errorTracker = $this->createMock(TypesenseErrorTracker::class);
//         $this->io = $this->createMock(SymfonyStyle::class);

//         $this->manager = new TypesenseManager(
//             $this->client,
//             $this->em,
//             $this->schemaGenerator,
//             $this->normalizer,
//             $this->errorTracker
//         );
//     }

//     public function testCreateCollectionForEntitySuccess(): void
//     {
//         $schema = ['name' => 'my_collection'];

//         $this->schemaGenerator->expects($this->once())
//             ->method('generate')
//             ->with('MyEntity')
//             ->willReturn($schema);

//         $this->client->expects($this->once())
//             ->method('createCollection')
//             ->with($schema);

//         $this->io->expects($this->exactly(2))
//             ->method('info')
//             ->with("Création de la collection 'my_collection'...");

//         $this->io->expects($this->once())
//             ->method('success')
//             ->with("Collection 'my_collection' créée avec succès.");

//         $this->manager->createCollectionForEntity('MyEntity', $this->io);
//     }

//     public function testCreateCollectionForEntityExceptionHandled(): void
//     {
//         $schema = ['name' => 'my_collection'];
//         $exception = new \Exception('fail');

//         $this->schemaGenerator->method('generate')->willReturn($schema);
//         $this->client->method('createCollection')->willThrowException($exception);

//         $this->errorTracker->expects($this->once())
//             ->method('trackError')
//             ->with(
//                 "Erreur lors de l'création pour la collection 'my_collection'",
//                 ['collection' => 'my_collection'],
//                 $exception
//             );

//         $this->io->expects($this->once())->method('info');
//         $this->io->expects($this->once())->method('error')->with($this->stringContains('fail'));

//         $this->expectException(\Exception::class);
//         $this->expectExceptionMessage('fail');

//         $this->manager->createCollectionForEntity('MyEntity', $this->io);
//     }

//     public function testDeleteCollectionForEntitySuccess(): void
//     {
//         $schema = ['name' => 'my_collection'];

//         $this->schemaGenerator->expects($this->once())
//             ->method('generate')
//             ->with('MyEntity')
//             ->willReturn($schema);

//         $this->client->expects($this->once())
//             ->method('deleteCollection')
//             ->with('my_collection');

//         $this->io->expects($this->once())
//             ->method('info')
//             ->with("Suppression de la collection 'my_collection'...");

//         $this->io->expects($this->once())
//             ->method('success')
//             ->with("Collection 'my_collection' supprimée avec succès.");

//         $this->manager->deleteCollectionForEntity('MyEntity', $this->io);
//     }

//     public function testDeleteCollectionForEntityNotFound(): void
//     {
//         $schema = ['name' => 'my_collection'];

//         $this->schemaGenerator->method('generate')->willReturn($schema);
//         $this->client->method('deleteCollection')->willThrowException(new ObjectNotFound());

//         $this->io->expects($this->once())
//             ->method('info')
//             ->with("Suppression de la collection 'my_collection'...");

//         $this->io->expects($this->once())
//             ->method('warning')
//             ->with("La collection 'my_collection' n'existait pas. Aucune action n'a été effectuée.");

//         $this->manager->deleteCollectionForEntity('MyEntity', $this->io);
//     }

//     public function testRecreateCollectionForEntity(): void
//     {
//         $this->manager = $this->getMockBuilder(TypesenseManager::class)
//             ->onlyMethods(['deleteCollectionForEntity', 'createCollectionForEntity'])
//             ->setConstructorArgs([
//                 $this->client,
//                 $this->em,
//                 $this->schemaGenerator,
//                 $this->normalizer,
//                 $this->errorTracker
//             ])->getMock();

//         $this->io->expects($this->once())->method('section')->with("Recréation de la collection pour l'entité 'MyEntity'");

//         $this->manager->expects($this->once())->method('deleteCollectionForEntity')->with('MyEntity', $this->io);
//         $this->manager->expects($this->once())->method('createCollectionForEntity')->with('MyEntity', $this->io);

//         $this->io->expects($this->once())->method('success')->with("Collection recréée avec succès.");

//         $this->manager->recreateCollectionForEntity('MyEntity', $this->io);
//     }

//     public function testReindexEntityCollectionSuccess(): void
//     {
//         $entity = new \stdClass();
//         $document = ['document' => ['id' => 1]];

//         $schema = ['name' => 'my_collection'];
//         $documents = [$entity];

//         $this->schemaGenerator->method('generate')->willReturn($schema);
//         $this->em->method('getRepository')->willReturnSelf();
//         $this->em->method('findAll')->willReturn($documents);

//         $this->normalizer->method('normalize')->with($entity)->willReturn($document);

//         $this->io->expects($this->once())->method('section');
//         $this->io->expects($this->once())->method('progressStart')->with(count($documents));
//         $this->io->expects($this->exactly(count($documents)))->method('progressAdvance');
//         $this->io->expects($this->once())->method('progressFinish');
//         $this->io->expects($this->once())->method('success')->with("1 documents de 'stdClass' ont été indexés avec succès.");

//         $importResults = [['success' => true]];
//         $this->client->method('importDocuments')->with('my_collection', [['id' => 1]])->willReturn($importResults);

//         $count = $this->manager->reindexEntityCollection('stdClass', $this->io);
//         $this->assertSame(1, $count);
//     }

//     public function testReindexEntityCollectionNoDocuments(): void
//     {
//         $schema = ['name' => 'my_collection'];

//         $this->schemaGenerator->method('generate')->willReturn($schema);
//         $this->em->method('getRepository')->willReturnSelf();
//         $this->em->method('findAll')->willReturn([]);

//         $this->io->expects($this->once())->method('warning')->with("Aucun document trouvé pour l'entité 'stdClass'.");

//         $count = $this->manager->reindexEntityCollection('stdClass', $this->io);
//         $this->assertSame(0, $count);
//     }

//     public function testReindexEntityCollectionNormalizationProducesNoDocuments(): void
//     {
//         $entity = new \stdClass();
//         $schema = ['name' => 'my_collection'];
//         $documents = [$entity];

//         $this->schemaGenerator->method('generate')->willReturn($schema);
//         $this->em->method('getRepository')->willReturnSelf();
//         $this->em->method('findAll')->willReturn($documents);

//         $this->normalizer->method('normalize')->with($entity)->willReturn(null);

//         $this->io->expects($this->once())->method('warning')->with("La normalisation n'a produit aucun document à indexer.");

//         $count = $this->manager->reindexEntityCollection('stdClass', $this->io);
//         $this->assertSame(0, $count);
//     }

//     public function testReindexEntityCollectionObjectNotFound(): void
//     {
//         $schema = ['name' => 'my_collection'];
//         $documents = [new \stdClass()];

//         $this->schemaGenerator->method('generate')->willReturn($schema);
//         $this->em->method('getRepository')->willReturnSelf();
//         $this->em->method('findAll')->willReturn($documents);

//         $this->normalizer->method('normalize')->willReturn(['document' => ['id' => 1]]);

//         $this->client->method('importDocuments')->willThrowException(new ObjectNotFound());

//         $this->errorTracker->expects($this->once())
//             ->method('trackError')
//             ->with(
//                 "Tentative d'indexation dans une collection inexistante",
//                 ['collection' => 'my_collection'],
//                 $this->isInstanceOf(ObjectNotFound::class)
//             );

//         $this->io->expects($this->once())->method('error')->with("La collection 'my_collection' n'existe pas. Veuillez la créer avant de lancer une indexation.");
//         $this->io->expects($this->once())->method('note')->with("Astuce : utilisez la commande 'micka17:typesense:manage recreate \"stdClass\"' pour la créer.");

//         $count = $this->manager->reindexEntityCollection('stdClass', $this->io);
//         $this->assertSame(0, $count);
//     }

//     public function testReindexEntityCollectionOtherExceptionHandled(): void
//     {
//         $schema = ['name' => 'my_collection'];
//         $documents = [new \stdClass()];
//         $exception = new \Exception('fail');

//         $this->schemaGenerator->method('generate')->willReturn($schema);
//         $this->em->method('getRepository')->willReturnSelf();
//         $this->em->method('findAll')->willReturn($documents);

//         $this->normalizer->method('normalize')->willReturn(['document' => ['id' => 1]]);
//         $this->client->method('importDocuments')->willThrowException($exception);

//         $this->errorTracker->expects($this->once())
//             ->method('trackError')
//             ->with(
//                 "Erreur lors de l'importation des documents pour la collection 'my_collection'",
//                 ['collection' => 'my_collection'],
//                 $exception
//             );

//         $this->io->expects($this->once())->method('error')->with($this->stringContains('fail'));

//         $count = $this->manager->reindexEntityCollection('stdClass', $this->io);
//         $this->assertSame(0, $count);
//     }
// }
