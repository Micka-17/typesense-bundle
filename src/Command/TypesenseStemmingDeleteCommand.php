<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\StemmingManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:stemming:delete',
    description: 'Delete a Typesense stemming dictionary.',
)]
class TypesenseStemmingDeleteCommand extends Command
{
    public function __construct(private readonly StemmingManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The stemming dictionary name.')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        if (!$input->getOption('yes') && !$io->confirm(sprintf('Delete stemming dictionary "%s"?', $name), false)) {
            $io->note('Aborted.');
            return Command::SUCCESS;
        }

        try {
            $this->manager->deleteDictionary($name);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Stemming dictionary "%s" deleted.', $name));

        return Command::SUCCESS;
    }
}
