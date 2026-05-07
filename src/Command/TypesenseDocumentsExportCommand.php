<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\TypesenseClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:documents:export',
    description: 'Export all documents from a Typesense collection as JSONL.',
)]
class TypesenseDocumentsExportCommand extends Command
{
    public function __construct(private readonly TypesenseClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('collection', InputArgument::REQUIRED, 'Collection name.')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (defaults to stdout).')
            ->addOption('filter-by', null, InputOption::VALUE_REQUIRED, 'Optional filter_by expression.')
            ->addOption('include-fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated field names to include.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $collection */
        $collection = $input->getArgument('collection');

        $queryParams = [];
        /** @var string|null $filterBy */
        $filterBy = $input->getOption('filter-by');
        if ($filterBy !== null) {
            $queryParams['filter_by'] = $filterBy;
        }
        /** @var string|null $includeFields */
        $includeFields = $input->getOption('include-fields');
        if ($includeFields !== null) {
            $queryParams['include_fields'] = $includeFields;
        }

        try {
            $jsonl = $this->client->exportDocuments($collection, $queryParams);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        /** @var string|null $outputPath */
        $outputPath = $input->getOption('output');

        if ($outputPath !== null) {
            file_put_contents($outputPath, $jsonl);
            $lineCount = $jsonl !== '' ? substr_count($jsonl, "\n") + 1 : 0;
            $io->success(sprintf('Exported %d document(s) to "%s".', $lineCount, $outputPath));
        } else {
            $output->write($jsonl);
        }

        return Command::SUCCESS;
    }
}
