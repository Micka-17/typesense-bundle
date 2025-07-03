<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\TypesenseClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'micka17:typesense:synonyms',
    description: 'Manage Typesense synonyms.',
)]
class TypesenseSynonymsCommand extends Command
{
    protected static $defaultName = 'micka17:typesense:synonyms';

    public function __construct(private readonly TypesenseClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Manage Typesense synonyms.')
            ->addArgument('action', InputArgument::REQUIRED, 'The action to perform (upsert, list, delete).')
            ->addArgument('synonym-id', InputArgument::OPTIONAL, 'The ID of the synonym.')
            ->addArgument('synonyms', InputArgument::OPTIONAL, 'Comma-separated list of words to be considered synonyms.')
            ->addOption('collection', 'c', InputOption::VALUE_REQUIRED, 'The name of the collection.')
            ->addOption('root', 'r', InputOption::VALUE_REQUIRED, 'The root word for a multi-way synonym.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $collectionName = $input->getOption('collection');

        try {
            switch ($action) {
                case 'upsert':
                    $this->upsertSynonym($input, $io, $collectionName);
                    break;
                case 'list':
                    $this->listSynonyms($io, $collectionName);
                    break;
                case 'delete':
                    $this->deleteSynonym($input, $io, $collectionName);
                    break;
                default:
                    $io->error('Invalid action. Use "upsert", "list", or "delete".');
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function upsertSynonym(InputInterface $input, SymfonyStyle $io, ?string $collectionName): void
    {
        $synonymId = $input->getArgument('synonym-id');
        $synonyms = $input->getArgument('synonyms');
        $root = $input->getOption('root');

        if (!$synonymId || !$synonyms) {
            $io->error('The "synonym-id" and "synonyms" arguments are required for the "upsert" action.');
            return;
        }

        $data = ['synonyms' => array_map('trim', explode(',', $synonyms))];
        if ($root) {
            $data['root'] = $root;
        }

        if ($collectionName) {
            $this->client->getOperations()->collections[$collectionName]->synonyms->upsert($synonymId, $data);
        } else {
            $this->client->getOperations()->synonyms->upsert($synonymId, $data);
        }

        $io->success(sprintf('Synonym "%s" has been upserted.', $synonymId));
    }

    private function listSynonyms(SymfonyStyle $io, ?string $collectionName): void
    {
        if ($collectionName) {
            $response = $this->client->getOperations()->collections[$collectionName]->synonyms->retrieve();
        } else {
            $response = $this->client->getOperations()->synonyms->retrieve();
        }

        $io->table(['ID', 'Synonyms', 'Root'], array_map(fn ($s) => [$s['id'], implode(', ', $s['synonyms']), $s['root'] ?? ''], $response['synonyms']));
    }

    private function deleteSynonym(InputInterface $input, SymfonyStyle $io, ?string $collectionName): void
    {
        $synonymId = $input->getArgument('synonym-id');

        if (!$synonymId) {
            $io->error('The "synonym-id" argument is required for the "delete" action.');
            return;
        }

        if ($collectionName) {
            $this->client->getOperations()->collections[$collectionName]->synonyms[$synonymId]->delete();
        } else {
            $this->client->getOperations()->synonyms[$synonymId]->delete();
        }

        $io->success(sprintf('Synonym "%s" has been deleted.', $synonymId));
    }
}
