<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\TypesenseClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:config:update',
    description: 'Dynamically update Typesense server configuration (e.g. cache-num-entries).',
)]
class TypesenseConfigUpdateCommand extends Command
{
    public function __construct(private readonly TypesenseClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'params',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'Key=value pairs (e.g. cache-num-entries=1000).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string[] $rawParams */
        $rawParams = $input->getArgument('params');
        $parsed = [];

        foreach ($rawParams as $raw) {
            if (!str_contains($raw, '=')) {
                $io->error(sprintf('Invalid parameter "%s": expected key=value format.', $raw));
                return Command::FAILURE;
            }
            [$key, $value] = explode('=', $raw, 2);
            $parsed[$key] = is_numeric($value) ? (int) $value : $value;
        }

        try {
            $result = $this->client->updateConfig($parsed);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Configuration updated.');
        $io->writeln(json_encode($result, JSON_PRETTY_PRINT) ?: '{}');

        return Command::SUCCESS;
    }
}
