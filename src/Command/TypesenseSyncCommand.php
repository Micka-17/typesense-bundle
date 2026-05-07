<?php

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\AnalyticsManager;
use Micka17\TypesenseBundle\Service\ConversationManager;
use Micka17\TypesenseBundle\Service\CurationSetManager;
use Micka17\TypesenseBundle\Service\NaturalLanguageSearchManager;
use Micka17\TypesenseBundle\Service\PresetManager;
use Micka17\TypesenseBundle\Service\StemmingManager;
use Micka17\TypesenseBundle\Service\SynonymSetManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'micka17:typesense:sync', description: 'Apply all configured Typesense resources.')]
class TypesenseSyncCommand extends Command
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
        private readonly SynonymSetManager $synonymSetManager,
        private readonly CurationSetManager $curationSetManager,
        private readonly PresetManager $presetManager,
        private readonly StemmingManager $stemmingManager,
        private readonly AnalyticsManager $analyticsManager,
        private readonly NaturalLanguageSearchManager $naturalLanguageSearchManager,
        private readonly ConversationManager $conversationManager,
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
            $this->apply($io, 'synonym sets', $this->synonymSets, fn() => $this->synonymSetManager->applyConfiguredSynonymSets($this->synonymSets));
            $this->apply($io, 'curation sets', $this->curationSets, fn() => $this->curationSetManager->applyConfiguredCurationSets($this->curationSets));
            $this->apply($io, 'presets', $this->presets, fn() => $this->presetManager->applyConfiguredPresets($this->presets));
            $this->apply($io, 'stemming dictionaries', $this->stemmingDictionaries, fn() => $this->stemmingManager->applyConfiguredDictionaries($this->stemmingDictionaries));
            $this->apply($io, 'analytics rules', $this->analyticsRules, fn() => $this->analyticsManager->applyConfiguredRules($this->analyticsRules));
            $this->apply($io, 'natural language search models', $this->nlSearchModels, fn() => $this->naturalLanguageSearchManager->applyConfiguredModels($this->nlSearchModels));
            $this->apply($io, 'conversation models', $this->conversationModels, fn() => $this->conversationManager->applyConfiguredModels($this->conversationModels));
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $io->success('Typesense resources synchronized.');

        return Command::SUCCESS;
    }

    /**
     * @param array<string, array<string, mixed>> $resources
     */
    private function apply(SymfonyStyle $io, string $label, array $resources, callable $callback): void
    {
        if ($resources === []) {
            $io->writeln(sprintf('  - Skipped %s: nothing configured.', $label));
            return;
        }

        $callback();
        $io->writeln(sprintf('  - Applied %d %s.', count($resources), $label));
    }
}
