<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\AnalyticsManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:analytics:rules:list',
    description: 'List all Typesense analytics rules.',
)]
class TypesenseAnalyticsRulesListCommand extends Command
{
    public function __construct(private readonly AnalyticsManager $manager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $response = $this->manager->listRules();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $rules = $response['rules'] ?? $response;

        if ($rules === []) {
            $io->info('No analytics rules found.');
            return Command::SUCCESS;
        }

        $io->table(
            ['Name', 'Type', 'Source', 'Destination'],
            array_map(static fn(array $r) => [
                $r['name'] ?? '—',
                $r['type'] ?? '—',
                $r['params']['source']['collections'][0] ?? '—',
                $r['params']['destination']['collection'] ?? '—',
            ], $rules),
        );

        return Command::SUCCESS;
    }
}
