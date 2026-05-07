<?php

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\CurationSetManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:curation-sets:apply',
    description: 'Apply configured Typesense curation sets.',
)]
class TypesenseCurationSetsApplyCommand extends Command
{
    /**
     * @param array<string, array<string, mixed>> $curationSets
     */
    public function __construct(
        private readonly CurationSetManager $manager,
        private readonly array $curationSets,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->curationSets === []) {
            $io->success('No curation sets configured.');
            return Command::SUCCESS;
        }

        try {
            $this->manager->applyConfiguredCurationSets($this->curationSets);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        foreach (array_keys($this->curationSets) as $name) {
            $io->writeln(sprintf('  - Applied curation set "%s"', $name));
        }

        $io->success('Curation sets applied.');

        return Command::SUCCESS;
    }
}
