<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\ConversationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:conversations:delete',
    description: 'Delete a Typesense conversation model.',
)]
class TypesenseConversationsDeleteCommand extends Command
{
    public function __construct(private readonly ConversationManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'The conversation model ID.')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getArgument('id');

        if (!$input->getOption('yes') && !$io->confirm(sprintf('Delete conversation model "%s"?', $id), false)) {
            $io->note('Aborted.');
            return Command::SUCCESS;
        }

        try {
            $this->manager->deleteModel($id);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Conversation model "%s" deleted.', $id));

        return Command::SUCCESS;
    }
}
