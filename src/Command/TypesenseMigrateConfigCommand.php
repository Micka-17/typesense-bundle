<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'micka17:typesense:migrate-config',
    description: 'Analyse the current configuration and generate the V2 equivalent YAML.',
)]
class TypesenseMigrateConfigCommand extends Command
{
    /**
     * @param array<string, mixed>               $legacySynonyms
     * @param array<string, array<string, mixed>> $synonymSets
     * @param array<string, array<string, mixed>> $curationSets
     * @param array<string, array<string, mixed>> $presets
     * @param array<string, array<string, mixed>> $stemmingDictionaries
     * @param array<string, array<string, mixed>> $analyticsRules
     * @param array<string, array<string, mixed>> $nlSearchModels
     * @param array<string, array<string, mixed>> $conversationModels
     */
    public function __construct(
        private readonly array $legacySynonyms,
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

    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write the generated YAML to a file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Typesense Bundle — V1 → V2 Configuration Migration');

        $warnings = [];
        $notices = [
            [
                'key' => 'API key actions',
                'severity' => 'MANUAL',
                'message' => 'If your API keys include "synonyms:*" scopes, replace them with "synonym_sets:*" for Typesense v30+.',
            ],
            [
                'key' => 'collection overrides',
                'severity' => 'MANUAL',
                'message' => 'Collection-level overrides (collections/{name}/overrides) are replaced by global curation_sets in v30+. Migrate any per-collection override to typesense.curation_sets.',
            ],
        ];
        $yaml = [];

        // --- Legacy synonyms ---
        if ($this->legacySynonyms !== []) {
            $warnings[] = [
                'key' => 'typesense.synonyms',
                'severity' => 'WARNING',
                'message' => sprintf(
                    '%d legacy synonym(s) found. Global synonym_sets replace per-collection synonyms in v30+.',
                    count($this->legacySynonyms),
                ),
            ];

            $yaml[] = $this->buildLegacySynonymsYaml();
        }

        $issues = [...$warnings, ...$notices];

        // --- Summary table ---
        $io->section('Issues detected');
        if ($warnings === []) {
            $io->success('No migration warnings. Your config already uses the V2 format.');
        }
        $io->table(
            ['Key', 'Severity', 'Action required'],
            array_map(static fn(array $i) => [$i['key'], $i['severity'], $i['message']], $issues),
        );

        // --- V2 already configured ---
        $io->section('V2 resources already configured');
        $io->table(
            ['Resource', 'Count', 'Status'],
            [
                ['synonym_sets', count($this->synonymSets), $this->synonymSets !== [] ? '✓ configured' : '— empty'],
                ['curation_sets', count($this->curationSets), $this->curationSets !== [] ? '✓ configured' : '— empty'],
                ['presets', count($this->presets), $this->presets !== [] ? '✓ configured' : '— empty'],
                ['stemming_dictionaries', count($this->stemmingDictionaries), $this->stemmingDictionaries !== [] ? '✓ configured' : '— empty'],
                ['analytics_rules', count($this->analyticsRules), $this->analyticsRules !== [] ? '✓ configured' : '— empty'],
                ['nl_search_models', count($this->nlSearchModels), $this->nlSearchModels !== [] ? '✓ configured' : '— empty'],
                ['conversation_models', count($this->conversationModels), $this->conversationModels !== [] ? '✓ configured' : '— empty'],
            ],
        );

        // --- Generated YAML ---
        if ($yaml !== []) {
            $yamlContent = implode("\n", $yaml);

            $io->section('Generated V2 YAML — add to your typesense.yaml');
            $io->block($yamlContent, null, 'fg=yellow', '  ');

            $outputFile = $input->getOption('output');
            if ($outputFile) {
                file_put_contents($outputFile, $yamlContent . "\n");
                $io->success(sprintf('YAML written to "%s".', $outputFile));
            }
        }

        // --- Next steps ---
        $io->section('Next steps');
        $io->listing([
            'Replace typesense.synonyms entries with typesense.synonym_sets (see generated YAML above).',
            'Update API key scopes: "synonyms:*" → "synonym_sets:*".',
            'Migrate any per-collection overrides to typesense.curation_sets.',
            'Run: php bin/console micka17:typesense:sync',
            'Run: php bin/console micka17:typesense:doctor',
        ]);

        return $warnings !== []
            ? Command::FAILURE
            : Command::SUCCESS;
    }

    private function buildLegacySynonymsYaml(): string
    {
        $lines = [];
        $lines[] = '# Generated from legacy typesense.synonyms — assign a set name below.';
        $lines[] = '# typesense.yaml';
        $lines[] = 'typesense:';
        $lines[] = '  synonym_sets:';
        $lines[] = '    my-collection:  # ← replace with your actual set name';
        $lines[] = '      items:';

        foreach ($this->legacySynonyms as $synonym) {
            $id = $synonym['id'] ?? 'synonym-' . uniqid();
            $lines[] = sprintf('        %s:', $id);
            if (!empty($synonym['root'])) {
                $lines[] = sprintf('          root: %s', $synonym['root']);
            }
            $synonymList = implode(', ', array_map(
                static fn(string $s) => "'$s'",
                $synonym['synonyms'] ?? [],
            ));
            $lines[] = sprintf('          synonyms: [%s]', $synonymList);
        }

        return implode("\n", $lines);
    }
}
