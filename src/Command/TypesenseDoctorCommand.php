<?php

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\TypesenseClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'micka17:typesense:doctor', description: 'Check Typesense connectivity and configured v30 resources.')]
class TypesenseDoctorCommand extends Command
{
    /**
     * @param array<string, array<string, mixed>> $synonymSets
     * @param array<string, array<string, mixed>> $curationSets
     * @param array<string, array<string, mixed>> $presets
     * @param array<string, array<string, mixed>> $stemmingDictionaries
     * @param array<string, array<string, mixed>> $analyticsRules
     * @param array<string, array<string, mixed>> $nlSearchModels
     * @param array<string, array<string, mixed>> $conversationModels
     */
    public function __construct(
        private readonly TypesenseClient $client,
        private readonly array $synonymSets,
        private readonly array $curationSets,
        private readonly array $presets,
        private readonly array $stemmingDictionaries,
        private readonly array $analyticsRules,
        private readonly array $nlSearchModels,
        private readonly array $conversationModels,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $health = $this->client->health();
        } catch (\Throwable $exception) {
            $io->error(sprintf('Typesense is not reachable: %s', $exception->getMessage()));
            return Command::FAILURE;
        }

        $io->success(sprintf('Typesense is reachable%s.', isset($health['ok']) ? ' (health: '.($health['ok'] ? 'ok' : 'not ok').')' : ''));
        $io->table(['Resource', 'Configured'], [
            ['Synonym sets', count($this->synonymSets)],
            ['Curation sets', count($this->curationSets)],
            ['Presets', count($this->presets)],
            ['Stemming dictionaries', count($this->stemmingDictionaries)],
            ['Analytics rules', count($this->analyticsRules)],
            ['NL search models', count($this->nlSearchModels)],
            ['Conversation models', count($this->conversationModels)],
        ]);

        return Command::SUCCESS;
    }
}
