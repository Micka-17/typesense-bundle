<?php

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\SynonymSetManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:synonym-sets:apply',
    description: 'Apply configured Typesense synonym sets.',
)]
class TypesenseSynonymSetsApplyCommand extends Command
{
    /**
     * @param array<string, array<string, mixed>> $synonymSets
     */
    public function __construct(
        private readonly SynonymSetManager $manager,
        private readonly array $synonymSets,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->synonymSets === []) {
            $io->success('No synonym sets configured.');
            return Command::SUCCESS;
        }

        try {
            $this->manager->applyConfiguredSynonymSets($this->synonymSets);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        foreach (array_keys($this->synonymSets) as $name) {
            $io->writeln(sprintf('  - Applied synonym set "%s"', $name));
        }

        $io->success('Synonym sets applied.');

        return Command::SUCCESS;
    }
}
