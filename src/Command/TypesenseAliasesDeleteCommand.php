<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\AliasManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:aliases:delete',
    description: 'Delete a Typesense collection alias.',
)]
class TypesenseAliasesDeleteCommand extends Command
{
    public function __construct(private readonly AliasManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The alias name.')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $name */
        $name = $input->getArgument('name');

        if (!$input->getOption('yes') && !$io->confirm(sprintf('Delete alias "%s"?', $name), false)) {
            $io->note('Aborted.');
            return Command::SUCCESS;
        }

        try {
            $this->manager->deleteAlias($name);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Alias "%s" deleted.', $name));

        return Command::SUCCESS;
    }
}
