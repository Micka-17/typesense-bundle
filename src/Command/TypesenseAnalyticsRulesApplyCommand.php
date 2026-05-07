<?php

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\AnalyticsManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'micka17:typesense:analytics:rules:apply', description: 'Apply configured Typesense analytics rules.')]
class TypesenseAnalyticsRulesApplyCommand extends Command
{
    /**
     * @param array<string, array<string, mixed>> $rules
     */
    public function __construct(private readonly AnalyticsManager $manager, private readonly array $rules)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->rules === []) {
            $io->success('No analytics rules configured.');
            return Command::SUCCESS;
        }

        try {
            $this->manager->applyConfiguredRules($this->rules);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $io->success('Analytics rules applied.');

        return Command::SUCCESS;
    }
}
