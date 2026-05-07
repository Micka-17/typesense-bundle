<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Dto\SchemaDiff;
use Micka17\TypesenseBundle\Service\SchemaDiffService;
use Micka17\TypesenseBundle\Service\TypesenseManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:schema:diff',
    description: 'Compare the live Typesense schema with the PHP attribute schema and optionally apply changes.',
)]
class TypesenseSchemaDiffCommand extends Command
{
    public function __construct(
        private readonly SchemaDiffService $diffService,
        private readonly TypesenseManager $manager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entity', InputArgument::REQUIRED, 'Entity FQCN (e.g. App\\Entity\\Product)')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Apply patchable changes (add/drop fields)')
            ->addOption('force-recreate', null, InputOption::VALUE_NONE, 'Recreate the collection entirely (destructive — loses all documents)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entityClass = $input->getArgument('entity');
        $apply = (bool) $input->getOption('apply');
        $forceRecreate = (bool) $input->getOption('force-recreate');

        if (!class_exists($entityClass)) {
            $io->error("Class '$entityClass' not found.");
            return Command::FAILURE;
        }

        try {
            $diff = $this->diffService->diff($entityClass);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $this->printDiff($io, $diff);

        if (!$diff->hasChanges()) {
            $io->success('Schema is up to date — no changes needed.');
            return Command::SUCCESS;
        }

        if ($forceRecreate) {
            $io->warning('--force-recreate: the collection will be deleted and re-created. All documents will be lost.');
            if (!$io->confirm('Are you sure?', false)) {
                $io->comment('Aborted.');
                return Command::SUCCESS;
            }
            $this->manager->recreateCollectionForEntity($entityClass, $io);
            return Command::SUCCESS;
        }

        if ($diff->requiresRecreation()) {
            $io->warning([
                'This diff contains field type conflicts or metadata changes that cannot be applied via PATCH.',
                'Use --force-recreate to recreate the collection (all documents will be lost).',
            ]);
            return Command::FAILURE;
        }

        if (!$apply) {
            $io->note('Run with --apply to apply the changes, or --force-recreate to recreate the collection.');
            return Command::SUCCESS;
        }

        try {
            $this->diffService->applyDiff($diff);
        } catch (\Throwable $e) {
            $io->error('Failed to apply diff: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Schema updated: %d field(s) added, %d field(s) dropped.',
            count($diff->fieldsToAdd),
            count($diff->fieldsToDrop),
        ));

        return Command::SUCCESS;
    }

    private function printDiff(SymfonyStyle $io, SchemaDiff $diff): void
    {
        $io->title(sprintf('Schema diff — collection: <info>%s</info>', $diff->collectionName));

        if (!$diff->hasChanges()) {
            return;
        }

        if ($diff->fieldsToAdd !== []) {
            $io->section('Fields to add');
            $rows = array_map(
                static fn(array $f) => [$f['name'], $f['type'], $f['optional'] ? 'yes' : 'no'],
                $diff->fieldsToAdd,
            );
            $io->table(['Name', 'Type', 'Optional'], $rows);
        }

        if ($diff->fieldsToDrop !== []) {
            $io->section('Fields to drop');
            $io->listing($diff->fieldsToDrop);
        }

        if ($diff->fieldConflicts !== []) {
            $io->section('Field conflicts (require recreation)');
            $rows = array_map(
                static fn(array $c) => [$c['name'], $c['live_type'], $c['generated_type']],
                $diff->fieldConflicts,
            );
            $io->table(['Name', 'Live type', 'Generated type'], $rows);
        }

        if ($diff->metadataChanged) {
            $io->section('Collection-level settings changed (require recreation)');
            $io->writeln('  default_sorting_field or enable_nested_fields differs from live schema.');
        }
    }
}
