<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Service;

class StemmingManager
{
    public function __construct(private readonly TypesenseClient $client) {}

    /**
     * @param array<string, array<string, mixed>> $dictionaries
     * @return array<string, mixed>
     */
    public function applyConfiguredDictionaries(array $dictionaries): array
    {
        $results = [];
        foreach ($dictionaries as $name => $config) {
            $results[$name] = $this->upsertDictionary($name, $config['words'] ?? []);
        }

        return $results;
    }

    /**
     * @param array<mixed> $words
     * @return array<string, mixed>
     */
    public function upsertDictionary(string $name, array $words): array
    {
        return $this->client->upsertStemmingDictionary($name, $words);
    }

    /**
     * @return array<string, mixed>
     */
    public function listDictionaries(): array
    {
        return $this->client->listStemmingDictionaries();
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveDictionary(string $name): array
    {
        return $this->client->retrieveStemmingDictionary($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteDictionary(string $name): array
    {
        return $this->client->deleteStemmingDictionary($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function importFromFile(string $name, string $filePath): array
    {
        if (!is_readable($filePath)) {
            throw new \InvalidArgumentException("Le fichier '$filePath' n'est pas lisible.");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Impossible de lire le fichier '$filePath'.");
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $words = match ($ext) {
            'json' => $this->parseJsonFile($content, $filePath),
            'csv' => $this->parseCsvWords($content),
            default => $this->parsePlainTextWords($content),
        };

        return $this->upsertDictionary($name, $words);
    }

    /**
     * @return array<mixed>
     */
    private function parseJsonFile(string $content, string $filePath): array
    {
        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Le fichier JSON '$filePath' est invalide.");
        }

        return $data;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseCsvWords(string $content): array
    {
        $words = [];
        foreach (explode("\n", trim($content)) as $line) {
            $cols = str_getcsv($line, ',', '"', '');
            if (count($cols) >= 2) {
                $words[] = ['word' => trim($cols[0]), 'root' => trim($cols[1])];
            }
        }

        return $words;
    }

    /**
     * @return array<int, string>
     */
    private function parsePlainTextWords(string $content): array
    {
        return array_values(array_filter(
            array_map('trim', explode("\n", $content)),
            static fn(string $line) => $line !== '',
        ));
    }
}
