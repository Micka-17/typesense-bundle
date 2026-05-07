<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\NaturalLanguageSearchManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:nl-search-models:delete',
    description: 'Delete a Typesense natural language search model.',
)]
class TypesenseNlSearchModelsDeleteCommand extends Command
{
    public function __construct(private readonly NaturalLanguageSearchManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'The NL search model ID.')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getArgument('id');

        if (!$input->getOption('yes') && !$io->confirm(sprintf('Delete NL search model "%s"?', $id), false)) {
            $io->note('Aborted.');
            return Command::SUCCESS;
        }

        try {
            $this->manager->deleteModel($id);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('NL search model "%s" deleted.', $id));

        return Command::SUCCESS;
    }
}
