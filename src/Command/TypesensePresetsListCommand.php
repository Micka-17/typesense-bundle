<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\PresetManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:presets:list',
    description: 'List all Typesense presets.',
)]
class TypesensePresetsListCommand extends Command
{
    public function __construct(private readonly PresetManager $manager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $response = $this->manager->listPresets();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $presets = $response['presets'] ?? $response;

        if ($presets === []) {
            $io->info('No presets found.');
            return Command::SUCCESS;
        }

        $io->table(
            ['Name', 'Parameters'],
            array_map(static fn(array $p) => [
                $p['name'] ?? '—',
                implode(', ', array_keys($p['value'] ?? [])),
            ], $presets),
        );

        return Command::SUCCESS;
    }
}
