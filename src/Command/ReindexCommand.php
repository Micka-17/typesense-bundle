<?php
// src/TypesenseBundle/Command/ReindexCommand.php

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
        
        foreach ($this->indexableEntities as $entityClass) {
            $io->section("Processing entity: $entityClass");
            
            $io->success("$entityClass re-indexed successfully.");
        }
        
        $io->success('All entities have been re-indexed.');
        
        return Command::SUCCESS;
    }
}