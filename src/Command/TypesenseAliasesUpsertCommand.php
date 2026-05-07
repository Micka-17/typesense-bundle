<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\AliasManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:aliases:upsert',
    description: 'Create or update a Typesense collection alias.',
)]
class TypesenseAliasesUpsertCommand extends Command
{
    public function __construct(private readonly AliasManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The alias name.')
            ->addArgument('collection', InputArgument::REQUIRED, 'The target collection name.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $name */
        $name = $input->getArgument('name');
        /** @var string $collection */
        $collection = $input->getArgument('collection');

        try {
            $this->manager->upsertAlias($name, $collection);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Alias "%s" → "%s" created/updated.', $name, $collection));

        return Command::SUCCESS;
    }
}
