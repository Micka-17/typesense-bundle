<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\KeysManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:keys:create',
    description: 'Create a Typesense API key (the key value is shown once).',
)]
class TypesenseKeysCreateCommand extends Command
{
    public function __construct(private readonly KeysManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Human-readable description.')
            ->addOption('actions', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Allowed actions (e.g. documents:search, collections:*).', [])
            ->addOption('collections', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Allowed collections (wildcard * allowed).', [])
            ->addOption('expires-in', null, InputOption::VALUE_REQUIRED, 'Expiry in seconds from now (0 = never, default: 0).', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $description */
        $description = $input->getOption('description');
        /** @var string[] $actions */
        $actions = $input->getOption('actions');
        /** @var string[] $collections */
        $collections = $input->getOption('collections');
        $expiresIn   = (int) $input->getOption('expires-in');

        if ($actions === []) {
            $io->error('--actions is required (e.g. --actions=documents:search).');
            return Command::FAILURE;
        }

        if ($collections === []) {
            $io->error('--collections is required (e.g. --collections=products or --collections=*).');
            return Command::FAILURE;
        }

        $config = [
            'description' => $description ?? '',
            'actions'     => $actions,
            'collections' => $collections,
            'expires_at'  => $expiresIn > 0 ? time() + $expiresIn : 0,
        ];

        try {
            $result = $this->manager->createKey($config);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $keyValue = isset($result['value']) ? (string) $result['value'] : '(unavailable)';

        $io->success(sprintf('API key created (ID: %s).', $result['id'] ?? '?'));
        $io->block(
            $keyValue,
            'KEY VALUE — COPY NOW',
            'fg=black;bg=yellow',
            ' ',
            true,
        );
        $io->caution('This value will NOT be shown again. Store it securely immediately.');

        return Command::SUCCESS;
    }
}
