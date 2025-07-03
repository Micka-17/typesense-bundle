<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Attribute\TypesenseSynonym;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'micka17:typesense:synonyms:apply',
    description: 'Apply synonyms from configuration and attributes to Typesense.',
)]
class TypesenseSynonymsApplyCommand extends Command
{
    protected static $defaultName = 'micka17:typesense:synonyms:apply';

    public function __construct(
        private readonly TypesenseClient $client,
        private readonly array $indexableEntities,
        private readonly array $globalSynonyms
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Apply synonyms from configuration and attributes to Typesense.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->applyGlobalSynonyms($io);
            $this->applyEntitySynonyms($io);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function applyGlobalSynonyms(SymfonyStyle $io): void
    {
        $io->section('Applying global synonyms...');

        foreach ($this->globalSynonyms as $synonym) {
            $this->client->getOperations()->synonyms->upsert($synonym['id'], [
                'root' => $synonym['root'] ?? null,
                'synonyms' => $synonym['synonyms'],
            ]);
            $io->writeln(sprintf('  - Upserted global synonym "%s"', $synonym['id']));
        }

        $io->success('Global synonyms applied.');
    }

    private function applyEntitySynonyms(SymfonyStyle $io): void
    {
        $io->section('Applying entity synonyms...');

        foreach ($this->indexableEntities as $entityClass) {
            $reflection = new ReflectionClass($entityClass);
            $attributes = $reflection->getAttributes(TypesenseSynonym::class);

            if (empty($attributes)) {
                continue;
            }

            $collectionName = $this->getCollectionName($reflection);
            if (!$collectionName) {
                $io->warning(sprintf('Skipping entity "%s" because it does not have a collection name.', $entityClass));
                continue;
            }

            $io->writeln(sprintf('Applying synonyms for collection "%s"...', $collectionName));

            foreach ($attributes as $attribute) {
                /** @var TypesenseSynonym $synonym */
                $synonym = $attribute->newInstance();
                $this->client->getOperations()->collections[$collectionName]->synonyms->upsert($synonym->id, [
                    'root' => $synonym->root,
                    'synonyms' => $synonym->synonyms,
                ]);
                $io->writeln(sprintf('  - Upserted synonym "%s" for collection "%s"', $synonym->id, $collectionName));
            }
        }

        $io->success('Entity synonyms applied.');
    }

    private function getCollectionName(ReflectionClass $reflection): ?string
    {
        $attributes = $reflection->getAttributes(TypesenseIndexable::class);
        if (empty($attributes)) {
            return null;
        }

        /** @var TypesenseIndexable $indexableAttribute */
        $indexableAttribute = $attributes[0]->newInstance();

        return $indexableAttribute->collection;
    }
}
