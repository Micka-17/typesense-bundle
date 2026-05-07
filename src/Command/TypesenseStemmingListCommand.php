<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\StemmingManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:stemming:list',
    description: 'List all Typesense stemming dictionaries.',
)]
class TypesenseStemmingListCommand extends Command
{
    public function __construct(private readonly StemmingManager $manager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $response = $this->manager->listDictionaries();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $dictionaries = $response['dictionaries'] ?? $response;

        if ($dictionaries === []) {
            $io->info('No stemming dictionaries found.');
            return Command::SUCCESS;
        }

        $io->table(
            ['Name'],
            array_map(static fn(array $d) => [$d['id'] ?? $d['name'] ?? '—'], $dictionaries),
        );

        return Command::SUCCESS;
    }
}
