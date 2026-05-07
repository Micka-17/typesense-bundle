<?php

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\PresetManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'micka17:typesense:presets:apply', description: 'Apply configured Typesense presets.')]
class TypesensePresetsApplyCommand extends Command
{
    /**
     * @param array<string, array<string, mixed>> $presets
     */
    public function __construct(private readonly PresetManager $manager, private readonly array $presets)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->presets === []) {
            $io->success('No presets configured.');
            return Command::SUCCESS;
        }

        try {
            $this->manager->applyConfiguredPresets($this->presets);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $io->success('Presets applied.');

        return Command::SUCCESS;
    }
}
