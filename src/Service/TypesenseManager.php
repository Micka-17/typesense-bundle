<?php

namespace Micka17\TypesenseBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;

class TypesenseManager
{
    public function __construct(
        private readonly TypesenseClient $client,
        private readonly EntityManagerInterface $em,
        private readonly SchemaGenerator $schemaGenerator,
        private readonly TypesenseNormalizer $normalizer,
        private readonly TypesenseErrorTracker $errorTracker
    ) {
    }

    public function createCollectionForEntity(string $entityClass, ?SymfonyStyle $io = null): void
    {
        $schema = $this->schemaGenerator->generate($entityClass);
        $collectionName = $schema['name'];

        $io?->info("Création de la collection '$collectionName'...");
        try {
            $this->client->createCollection($schema);
            $io?->success("Collection '$collectionName' créée avec succès.");
        } catch (\Exception $e) {
            $this->handleException($e, "création", $collectionName, $io);
        }
    }

    public function deleteCollectionForEntity(string $entityClass, ?SymfonyStyle $io = null): void
    {
        $schema = $this->schemaGenerator->generate($entityClass);
        $collectionName = $schema['name'];

        $io?->info("Suppression de la collection '$collectionName'...");
        try {
            $this->client->deleteCollection($collectionName);
            $io?->success("Collection '$collectionName' supprimée avec succès.");
        } catch (ObjectNotFound $e) {
            $io?->warning("La collection '$collectionName' n'existait pas. Aucune action n'a été effectuée.");
        } catch (\Exception $e) {
            $this->handleException($e, "suppression", $collectionName, $io);
        }
    }

    public function recreateCollectionForEntity(string $entityClass, ?SymfonyStyle $io = null): void
    {
        $io?->section("Recréation de la collection pour l'entité '$entityClass'");
        
        try {
            $this->deleteCollectionForEntity($entityClass, $io);
            sleep(1);
            $this->createCollectionForEntity($entityClass, $io);
            $io?->success("Collection recréée avec succès.");
        } catch (\Exception $e) {
            $io?->error("La recréation de la collection a échoué. Voir l'erreur ci-dessus.");
        }
    }

    /**
     * @param class-string $entityClass
     * @param int $batchSize Number of entities loaded per iteration (avoids OOM on large tables).
     *                       Call $em->clear() between batches — lazy-loaded associations will be
     *                       re-fetched automatically by Doctrine on next access.
     */
    public function reindexEntityCollection(string $entityClass, ?SymfonyStyle $io = null, int $batchSize = 1000): int
    {
        if ($batchSize < 1) {
            throw new \InvalidArgumentException('batchSize must be >= 1.');
        }

        $schema = $this->schemaGenerator->generate($entityClass);
        $collectionName = $schema['name'];

        $io?->section("Indexation de l'entité '$entityClass' dans la collection '$collectionName'");

        /** @var \Doctrine\ORM\EntityRepository<object> $repo */
        $repo = $this->em->getRepository($entityClass);
        $totalCount = $repo->count([]);

        if ($totalCount === 0) {
            $io?->warning("Aucun document trouvé pour l'entité '$entityClass'.");
            return 0;
        }

        $io?->progressStart($totalCount);

        $successCount = 0;
        $failureCount = 0;
        $firstError = '';
        $offset = 0;

        try {
            while ($offset < $totalCount) {
                /** @var list<object> $batch */
                $batch = $repo->findBy([], null, $batchSize, $offset);

                if ($batch === []) {
                    break;
                }

                $typesenseDocs = [];
                foreach ($batch as $entity) {
                    $normalizedResult = $this->normalizer->normalize($entity);
                    if ($normalizedResult && isset($normalizedResult['document'])) {
                        $typesenseDocs[] = $normalizedResult['document'];
                    }
                    $io?->progressAdvance();
                }

                if ($typesenseDocs !== []) {
                    $importResults = $this->client->importDocuments($collectionName, $typesenseDocs);
                    foreach ($importResults as $result) {
                        if ($result['success'] === true) {
                            $successCount++;
                        } else {
                            $failureCount++;
                            if ($firstError === '') {
                                $firstError = $result['error'] ?? 'Raison inconnue.';
                            }
                        }
                    }
                }

                // Free first-level cache to avoid OOM on large datasets.
                $this->em->clear();
                $offset += $batchSize;
            }

            $io?->progressFinish();

            if ($successCount === 0 && $failureCount === 0) {
                $io?->warning("La normalisation n'a produit aucun document à indexer.");
                return 0;
            }

            if ($failureCount > 0) {
                $io?->warning("L'importation est terminée, mais $failureCount documents sur $totalCount ont été rejetés par Typesense.");
                $io?->note("Première erreur rapportée : " . $firstError);
            } else {
                $io?->success("$successCount documents de '$entityClass' ont été indexés avec succès.");
            }

            return $successCount;

        } catch (ObjectNotFound $e) {
            $errorMessage = "La collection '$collectionName' n'existe pas. Veuillez la créer avant de lancer une indexation.";
            $suggestion = "Astuce : utilisez la commande 'micka17:typesense:manage recreate \"$entityClass\"' pour la créer.";

            $this->errorTracker->trackError(
                "Tentative d'indexation dans une collection inexistante",
                ['collection' => $collectionName],
                $e
            );

            $io?->error($errorMessage);
            $io?->note($suggestion);

            return 0;

        } catch (\Exception $e) {
            $this->handleException($e, "importation des documents", $collectionName, $io);
            return 0;
        }
    }

    private function handleException(\Exception $e, string $action, string $collectionName, ?SymfonyStyle $io): void
    {
        $errorMessage = sprintf(
            "Erreur lors de l'%s pour la collection '%s'",
            $action,
            $collectionName
        );

        $this->errorTracker->trackError(
            $errorMessage,
            ['collection' => $collectionName],
            $e
        );

        $consoleMessage = $errorMessage . " : " . $e->getMessage();
        $nodeDetails = $this->errorTracker->getFormattedNodeDetails($e);
        
        if ($nodeDetails) {
            $consoleMessage .= " (" . $nodeDetails . ")";
        }

        $io?->error($consoleMessage);

        throw $e;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public function createCollection(array $schema): array
    {
        return $this->client->createCollection($schema);
    }

    /** @return array<string, mixed> */
    public function deleteCollection(string $name): array
    {
        return $this->client->deleteCollection($name);
    }

    public function collectionExists(string $name): bool
    {
        return $this->client->collectionExists($name);
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    public function createOrUpdateDocument(string $collection, array $document): array
    {
        return $this->client->createOrUpdateDocument($collection, $document);
    }

    /** @return array<string, mixed> */
    public function deleteDocument(string $collection, string $id): array
    {
        return $this->client->deleteDocument($collection, $id);
    }

    public function documentExists(string $collection, string $id): bool
    {
        return $this->client->documentExists($collection, $id);
    }
    
    public function getClient(): TypesenseClient
    {
        return $this->client;
    }

    public function isClusterEnabled(): bool
    {
        return $this->client->isClusterEnabled();
    }

    public function getSchemaGenerator(): SchemaGenerator
    {
        return $this->schemaGenerator;
    }
}