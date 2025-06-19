<?php
// src/TypesenseBundle/Service/TypesenseManager.php

namespace Micka17\TypesenseBundle\Service;

use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Micka17\TypesenseBundle\Service\SchemaGenerator;
use Micka17\TypesenseBundle\Service\TypesenseNormalizer;

class TypesenseManager
{
    public function __construct(
        private readonly Client $client,
        private readonly EntityManagerInterface $em,
        private readonly SchemaGenerator $schemaGenerator,
        private readonly TypesenseNormalizer $normalizer
    ) {
    }

     /**
     * Crée une collection dans Typesense à partir du schéma d'une entité.
     */
    public function createCollectionFromEntity(string $entityClass, ?SymfonyStyle $io = null): void
    {
        $schema = $this->schemaGenerator->generate($entityClass);
        $collectionName = $schema['name'];

        try {
            $io?->info("Suppression de l'ancienne collection '$collectionName' si elle existe...");
            $this->client->collections[$collectionName]->delete();
            $io?->comment("Ancienne collection supprimée.");
        } catch (ObjectNotFound) {
            $io?->comment("Aucune ancienne collection à supprimer.");
        }

        $io?->info("Création de la nouvelle collection '$collectionName'...");
        $this->client->collections->create($schema);
        $io?->success("Collection '$collectionName' créée avec succès.");
    }

    /**
     * Indexe tous les documents d'une entité dans Typesense.
     */
    public function reindexEntityCollection(string $entityClass, ?SymfonyStyle $io = null): int
    {
        $schema = $this->schemaGenerator->generate($entityClass);
        $collectionName = $schema['name'];

        $io?->section("Indexation de l'entité '$entityClass' dans la collection '$collectionName'");

        $repository = $this->em->getRepository($entityClass);
        $documents = $repository->findAll();
        $count = count($documents);

        if ($count === 0) {
            $io?->warning("Aucun document trouvé pour l'entité '$entityClass'.");
            return 0;
        }

        $io?->progressStart($count);

        $typesenseDocs = [];
        foreach ($documents as $entity) {
            $normalizedData = $this->normalizer->normalize($entity);
            if ($normalizedData) {
                $typesenseDocs[] = $normalizedData['data'];
            }
            $io?->progressAdvance();
        }

        $this->client->collections[$collectionName]->documents()->import($typesenseDocs, ['action' => 'upsert']);

        $io?->progressFinish();
        $io?->success("$count documents de '$entityClass' ont été indexés.");

        return $count;
    }

    public function createCollection(array $schema): array
    {
        return $this->client->collections->create($schema);
    }

    public function deleteCollection(string $name): array
    {
        return $this->client->collections[$name]->delete();
    }

    public function collectionExists(string $name): bool
    {
        try {
            $this->client->collections[$name]->retrieve();
            return true;
        } catch (ObjectNotFound) {
            return false;
        }
    }

    public function createOrUpdateDocument(string $collection, array $document): array
    {
        return $this->client->collections[$collection]->documents->upsert($document);
    }

    public function deleteDocument(string $collection, string $id): array
    {
        return $this->client->collections[$collection]->documents[$id]->delete();
    }

    public function documentExists(string $collection, string $id): bool
    {
        try {
            $this->client->collections[$collection]->documents[$id]->retrieve();
            return true;
        } catch (ObjectNotFound) {
            return false;
        }
    }
    
    public function getClient(): Client
    {
        return $this->client;
    }
}