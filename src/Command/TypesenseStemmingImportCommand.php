<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\StemmingManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:stemming:import',
    description: 'Import a stemming dictionary from a local file (.json, .csv, or plain text).',
)]
class TypesenseStemmingImportCommand extends Command
{
    public function __construct(private readonly StemmingManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The stemming dictionary name.')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the file (.json, .csv, or plain text).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $file = $input->getArgument('file');

        if (!file_exists($file)) {
            $io->error(sprintf('File not found: "%s".', $file));
            return Command::FAILURE;
        }

        try {
            $this->manager->importFromFile($name, $file);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Stemming dictionary "%s" imported from "%s".', $name, $file));

        return Command::SUCCESS;
    }
}
