<?php

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\NaturalLanguageSearchManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'micka17:typesense:nl-search-models:apply', description: 'Apply configured Typesense natural language search models.')]
class TypesenseNlSearchModelsApplyCommand extends Command
{
    /**
     * @param array<string, array<string, mixed>> $models
     */
    public function __construct(private readonly NaturalLanguageSearchManager $manager, private readonly array $models)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->models === []) {
            $io->success('No natural language search models configured.');
            return Command::SUCCESS;
        }

        try {
            $this->manager->applyConfiguredModels($this->models);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $io->success('Natural language search models applied.');

        return Command::SUCCESS;
    }
}
