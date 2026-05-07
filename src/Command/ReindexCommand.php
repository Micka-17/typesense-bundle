<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\TypesenseManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'typesense:reindex', description: 'Re-indexes configured entities into Typesense.')]
class ReindexCommand extends Command
{
    public function __construct(private readonly TypesenseManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entity', InputArgument::REQUIRED, 'FQCN of the entity to reindex (e.g. App\\Entity\\Product).')
            ->addOption('create-collection', null, InputOption::VALUE_NONE, 'Create the collection before indexing.')
            ->addOption('reindex', null, InputOption::VALUE_NONE, 'Index all documents.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of entities per batch.', 1000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $entityClass */
        $entityClass = $input->getArgument('entity');
        $doCreate    = (bool) $input->getOption('create-collection');
        $doReindex   = (bool) $input->getOption('reindex');
        $batchSize   = (int) $input->getOption('batch-size');

        if (!$doCreate && !$doReindex) {
            $io->warning('Aucune action spécifiée. Utilisez --create-collection ou --reindex.');
            return Command::INVALID;
        }

        if ($batchSize < 1) {
            $io->error('--batch-size must be >= 1.');
            return Command::FAILURE;
        }

        if ($doCreate) {
            $this->manager->createCollectionForEntity($entityClass, $io);
        }

        if ($doReindex) {
            $this->manager->reindexEntityCollection($entityClass, $io, $batchSize);
        }

        return Command::SUCCESS;
    }
}
