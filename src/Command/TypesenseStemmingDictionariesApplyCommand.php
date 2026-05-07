<?php

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\StemmingManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'micka17:typesense:stemming:apply', description: 'Apply configured Typesense stemming dictionaries.')]
class TypesenseStemmingDictionariesApplyCommand extends Command
{
    /**
     * @param array<string, array<string, mixed>> $dictionaries
     */
    public function __construct(private readonly StemmingManager $manager, private readonly array $dictionaries)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->dictionaries === []) {
            $io->success('No stemming dictionaries configured.');
            return Command::SUCCESS;
        }

        try {
            $this->manager->applyConfiguredDictionaries($this->dictionaries);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $io->success('Stemming dictionaries applied.');

        return Command::SUCCESS;
    }
}
