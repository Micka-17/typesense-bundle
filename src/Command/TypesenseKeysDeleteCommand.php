<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\KeysManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:keys:delete',
    description: 'Delete a Typesense API key by ID.',
)]
class TypesenseKeysDeleteCommand extends Command
{
    public function __construct(private readonly KeysManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'Numeric ID of the key to delete.')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $rawId */
        $rawId = $input->getArgument('id');

        if (!ctype_digit($rawId)) {
            $io->error(sprintf('Invalid key ID "%s": must be a positive integer.', $rawId));
            return Command::FAILURE;
        }

        $id = (int) $rawId;

        if (!$input->getOption('yes') && !$io->confirm(sprintf('Delete API key #%d?', $id), false)) {
            $io->note('Aborted.');
            return Command::SUCCESS;
        }

        try {
            $this->manager->deleteKey($id);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('API key #%d deleted.', $id));

        return Command::SUCCESS;
    }
}
