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

    public function reindexEntityCollection(string $entityClass, ?SymfonyStyle $io = null): int
    {
        $schema = $this->schemaGenerator->generate($entityClass);
        $collectionName = $schema['name'];

        $io?->section("Indexation de l'entité '$entityClass' dans la collection '$collectionName'");

        $documents = $this->em->getRepository($entityClass)->findAll();
        $totalCount = count($documents);

        if ($totalCount === 0) {
            $io?->warning("Aucun document trouvé pour l'entité '$entityClass'.");
            return 0;
        }

        $io?->progressStart($totalCount);
        $typesenseDocs = [];
        foreach ($documents as $entity) {
            $normalizedResult = $this->normalizer->normalize($entity);
            if ($normalizedResult && isset($normalizedResult['document'])) {
                $typesenseDocs[] = $normalizedResult['document'];
            }
            $io?->progressAdvance();
        }
        $io?->progressFinish();

        if (empty($typesenseDocs)) {
            $io?->warning("La normalisation n'a produit aucun document à indexer.");
            return 0;
        }

        try {
            $importResults = $this->client->importDocuments($collectionName, $typesenseDocs);
            
            $successCount = 0;
            $failureCount = 0;
            $firstError = '';

            foreach ($importResults as $result) {
                if ($result['success'] === true) {
                    $successCount++;
                } else {
                    $failureCount++;
                    if (empty($firstError)) {
                        $firstError = $result['error'] ?? 'Raison inconnue.';
                    }
                }
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

    public function createCollection(array $schema): array
    {
        return $this->client->createCollection($schema);
    }

    public function deleteCollection(string $name): array
    {
        return $this->client->deleteCollection($name);
    }

    public function collectionExists(string $name): bool
    {
        return $this->client->collectionExists($name);
    }

    public function createOrUpdateDocument(string $collection, array $document): array
    {
        return $this->client->createOrUpdateDocument($collection, $document);
    }

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