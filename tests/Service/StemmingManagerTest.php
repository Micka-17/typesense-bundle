<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Tests\Service;

use Micka17\TypesenseBundle\Service\StemmingManager;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class StemmingManagerTest extends TestCase
{
    private TypesenseClient $client;
    private StemmingManager $manager;

    protected function setUp(): void
    {
        $this->client = $this->createMock(TypesenseClient::class);
        $this->manager = new StemmingManager($this->client);
    }

    public function testApplyConfiguredDictionaries(): void
    {
        $this->client->expects($this->once())
            ->method('upsertStemmingDictionary')
            ->with('fr-verbs', [['word' => 'aimer', 'root' => 'aim']])
            ->willReturn(['id' => 'fr-verbs']);

        $this->manager->applyConfiguredDictionaries([
            'fr-verbs' => ['words' => [['word' => 'aimer', 'root' => 'aim']]],
        ]);
    }

    public function testApplyConfiguredDictionariesEmptyWords(): void
    {
        $this->client->expects($this->once())
            ->method('upsertStemmingDictionary')
            ->with('empty', [])
            ->willReturn([]);

        $this->manager->applyConfiguredDictionaries(['empty' => []]);
    }

    public function testUpsertDictionary(): void
    {
        $this->client->expects($this->once())
            ->method('upsertStemmingDictionary')
            ->with('dict', ['word' => 'run'])
            ->willReturn(['id' => 'dict']);

        $this->manager->upsertDictionary('dict', ['word' => 'run']);
    }

    public function testListDictionaries(): void
    {
        $expected = ['dictionaries' => [['id' => 'd1']]];
        $this->client->expects($this->once())->method('listStemmingDictionaries')->willReturn($expected);

        $this->assertSame($expected, $this->manager->listDictionaries());
    }

    public function testRetrieveDictionary(): void
    {
        $this->client->expects($this->once())
            ->method('retrieveStemmingDictionary')
            ->with('d1')
            ->willReturn(['id' => 'd1']);

        $this->manager->retrieveDictionary('d1');
    }

    public function testDeleteDictionary(): void
    {
        $this->client->expects($this->once())
            ->method('deleteStemmingDictionary')
            ->with('d1')
            ->willReturn(['id' => 'd1']);

        $this->manager->deleteDictionary('d1');
    }

    public function testImportFromJsonFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'stemming_') . '.json';
        file_put_contents($tmpFile, json_encode([['word' => 'running', 'root' => 'run']]));

        $this->client->expects($this->once())
            ->method('upsertStemmingDictionary')
            ->with('test-dict', [['word' => 'running', 'root' => 'run']])
            ->willReturn(['id' => 'test-dict']);

        $this->manager->importFromFile('test-dict', $tmpFile);

        unlink($tmpFile);
    }

    public function testImportFromCsvFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'stemming_') . '.csv';
        file_put_contents($tmpFile, "running,run\nwalking,walk\n");

        $this->client->expects($this->once())
            ->method('upsertStemmingDictionary')
            ->with('csv-dict', [
                ['word' => 'running', 'root' => 'run'],
                ['word' => 'walking', 'root' => 'walk'],
            ])
            ->willReturn(['id' => 'csv-dict']);

        $this->manager->importFromFile('csv-dict', $tmpFile);

        unlink($tmpFile);
    }

    public function testImportFromMissingFileThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->importFromFile('dict', '/nonexistent/path.json');
    }
}
