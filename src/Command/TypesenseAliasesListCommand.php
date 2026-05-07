<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\AliasManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:aliases:list',
    description: 'List all Typesense collection aliases.',
)]
class TypesenseAliasesListCommand extends Command
{
    public function __construct(private readonly AliasManager $manager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $response = $this->manager->listAliases();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $aliases = $response['aliases'] ?? $response;

        if ($aliases === []) {
            $io->info('No aliases found.');
            return Command::SUCCESS;
        }

        $io->table(
            ['Name', 'Collection'],
            array_map(static fn(array $a) => [
                $a['name'] ?? '—',
                $a['collection_name'] ?? '—',
            ], $aliases),
        );

        return Command::SUCCESS;
    }
}
