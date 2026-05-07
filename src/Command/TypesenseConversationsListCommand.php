<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\ConversationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:conversations:list',
    description: 'List all Typesense conversation models.',
)]
class TypesenseConversationsListCommand extends Command
{
    public function __construct(private readonly ConversationManager $manager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $response = $this->manager->listModels();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $models = $response['models'] ?? $response;

        if ($models === []) {
            $io->info('No conversation models found.');
            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Model Name', 'Provider'],
            array_map(static fn(array $m) => [
                $m['id'] ?? '—',
                $m['model_name'] ?? '—',
                $m['provider'] ?? '—',
            ], $models),
        );

        return Command::SUCCESS;
    }
}
