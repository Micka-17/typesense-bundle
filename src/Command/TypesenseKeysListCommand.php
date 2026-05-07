<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\KeysManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:keys:list',
    description: 'List all Typesense API keys (key values are not shown).',
)]
class TypesenseKeysListCommand extends Command
{
    public function __construct(private readonly KeysManager $manager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $response = $this->manager->listKeys();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $keys */
        $keys = $response['keys'] ?? $response;

        if ($keys === []) {
            $io->info('No API keys found.');
            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Description', 'Actions', 'Collections', 'Expires At'],
            array_map(static function (array $k): array {
                $expiresAt = isset($k['expires_at']) && (int) $k['expires_at'] > 0
                    ? date('Y-m-d H:i', (int) $k['expires_at'])
                    : 'never';

                return [
                    $k['id'] ?? '—',
                    $k['description'] ?? '—',
                    implode(', ', (array) ($k['actions'] ?? [])),
                    implode(', ', (array) ($k['collections'] ?? [])),
                    $expiresAt,
                ];
            }, $keys),
        );

        return Command::SUCCESS;
    }
}
