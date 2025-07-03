<?php

namespace Micka17\TypesenseBundle\Tests\DependencyInjection;

use Micka17\TypesenseBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigurationTest extends TestCase
{
    private function processConfiguration(array $configs): array
    {
        $processor = new Processor();
        $configuration = new Configuration();
        return $processor->processConfiguration($configuration, [$configs]);
    }

    public function testMinimalValidConfiguration(): void
    {
        $config = [
            'api_key' => 'my-secret',
            'cluster' => [
                'nodes' => [
                    ['host' => 'localhost']
                ]
            ]
        ];

        $result = $this->processConfiguration($config);

        $this->assertSame('my-secret', $result['api_key']);
        $this->assertTrue($result['auto_update']);
        $this->assertFalse($result['cluster']['enabled']);
        $this->assertSame('master_only', $result['cluster']['read_preference']);
    }

    public function testInvalidLogLevelThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/Le niveau de log doit être un niveau valide/');

        $this->processConfiguration([
            'api_key' => 'my-secret',
            'error_tracking' => [
                'log_level' => 'invalid_level'
            ],
            'cluster' => [
                'nodes' => [['host' => 'localhost']]
            ]
        ]);
    }

    public function testMissingApiKeyThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->processConfiguration([
            'cluster' => [
                'nodes' => [['host' => 'localhost']]
            ]
        ]);
    }

    public function testInvalidNodeErrorFieldThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/Champ de suivi invalide/');

        $this->processConfiguration([
            'api_key' => 'my-secret',
            'error_tracking' => [
                'node_error_fields' => ['invalid_field']
            ],
            'cluster' => [
                'nodes' => [['host' => 'localhost']]
            ]
        ]);
    }
}
