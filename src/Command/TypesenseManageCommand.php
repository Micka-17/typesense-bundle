<?php
// Fichier : micka-17/typesense-bundle/src/Command/TypesenseManageCommand.php

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\TypesenseManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:manage',
    description: 'Gère les collections et l\'indexation Typesense basées sur les entités.'
)]
class TypesenseManageCommand extends Command
{
    public function __construct(private readonly TypesenseManager $typesenseManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entity', InputArgument::REQUIRED, 'La classe de l\'entité à gérer (ex: App\Entity\Cocktail)')
            ->addOption('create-collection', null, InputOption::VALUE_NONE, 'Crée (ou recrée) la collection pour cette entité.')
            ->addOption('reindex', null, InputOption::VALUE_NONE, 'Indexe tous les documents de cette entité.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entityClass = $input->getArgument('entity');

        if (!class_exists($entityClass)) {
            $io->error("La classe '$entityClass' n'existe pas.");
            return Command::FAILURE;
        }

        if ($input->getOption('create-collection')) {
            $this->typesenseManager->createCollectionFromEntity($entityClass, $io);
        }

        if ($input->getOption('reindex')) {
            $this->typesenseManager->reindexEntityCollection($entityClass, $io);
        }

        if (!$input->getOption('create-collection') && !$input->getOption('reindex')) {
            $io->warning('Aucune action spécifiée. Utilisez --create-collection ou --reindex.');
        }

        return Command::SUCCESS;
    }
}