<?php

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\TypesenseManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'typesense:reindex', description: 'Re-indexes all configured entities into Typesense.')]
class ReindexCommand extends Command
{
    public function __construct(
        private readonly array $indexableEntities,
        private readonly EntityManagerInterface $em,
        private readonly TypesenseManager $manager
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $entityClass = $input->getArgument('entity');
        $doCreate = $input->getOption('create-collection');
        $doReindex = $input->getOption('reindex');
    
        if (!$doCreate && !$doReindex) {
            $io->warning('Aucune action spécifiée. Utilisez --create-collection ou --reindex.');
            return Command::INVALID;
        }
    
        if ($doCreate) {
            $this->manager->createCollectionFromEntity($entityClass, $io);
        }
    
        if ($doReindex) {
            $this->manager->reindexEntityCollection($entityClass, $io);
        }
    
        return Command::SUCCESS;
    }
}