<?php

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
    public function __construct(private readonly TypesenseManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'L\'action à effectuer : create, delete, recreate, reindex')
            ->addArgument('entity', InputArgument::REQUIRED, 'La classe de l\'entité à gérer (ex: App\Entity\Cocktail)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $entityClass = $input->getArgument('entity');

        if (!class_exists($entityClass)) {
            $io->error("La classe '$entityClass' n'existe pas.");
            return Command::FAILURE;
        }

        switch ($action) {
            case 'create':
                $this->manager->createCollectionForEntity($entityClass, $io);
                break;
            case 'delete':
                $this->manager->deleteCollectionForEntity($entityClass, $io);
                break;
            case 'recreate':
                $this->manager->recreateCollectionForEntity($entityClass, $io);
                break;
            case 'reindex':
                $this->manager->reindexEntityCollection($entityClass, $io);
                break;
            default:
                $io->error("Action non valide : '$action'. Actions possibles : create, delete, recreate, reindex.");
                return Command::INVALID;
        }

        return Command::SUCCESS;
    }
}