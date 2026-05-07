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
        $this->assertTrue($result['auto_update']['enabled']);
        $this->assertSame('sync', $result['auto_update']['mode']);
        $this->assertFalse($result['cluster']['enabled']);
        $this->assertSame('leader_only', $result['cluster']['read_preference']);
    }

    public function testAutoUpdateBoolTrueBackwardCompat(): void
    {
        $result = $this->processConfiguration([
            'api_key' => 'key',
            'auto_update' => true,
            'cluster' => ['nodes' => [['host' => 'localhost']]],
        ]);

        $this->assertTrue($result['auto_update']['enabled']);
        $this->assertSame('sync', $result['auto_update']['mode']);
    }

    public function testAutoUpdateBoolFalseBackwardCompat(): void
    {
        $result = $this->processConfiguration([
            'api_key' => 'key',
            'auto_update' => false,
            'cluster' => ['nodes' => [['host' => 'localhost']]],
        ]);

        $this->assertFalse($result['auto_update']['enabled']);
    }

    public function testAutoUpdateAsyncMode(): void
    {
        $result = $this->processConfiguration([
            'api_key' => 'key',
            'auto_update' => ['enabled' => true, 'mode' => 'async'],
            'cluster' => ['nodes' => [['host' => 'localhost']]],
        ]);

        $this->assertTrue($result['auto_update']['enabled']);
        $this->assertSame('async', $result['auto_update']['mode']);
    }

    public function testAutoUpdateInvalidModeThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/auto_update.mode must be/');

        $this->processConfiguration([
            'api_key' => 'key',
            'auto_update' => ['enabled' => true, 'mode' => 'invalid'],
            'cluster' => ['nodes' => [['host' => 'localhost']]],
        ]);
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

    public function testTypesenseV30ResourcesConfiguration(): void
    {
        $result = $this->processConfiguration([
            'api_key' => 'my-secret',
            'cluster' => [
                'nodes' => [['host' => 'localhost']]
            ],
            'synonym_sets' => [
                'products' => [
                    'items' => [
                        'size' => [
                            'synonyms' => ['large', 'big', 'huge'],
                        ],
                        'color' => [
                            'root' => 'primary_color',
                            'synonyms' => ['red', 'blue', 'green'],
                        ],
                    ],
                ],
            ],
            'curation_sets' => [
                'products' => [
                    'items' => [
                        'promote-iphone' => [
                            'rule' => ['query' => 'iphone'],
                            'includes' => [['id' => 'iphone-15', 'position' => 1]],
                        ],
                    ],
                ],
            ],
            'presets' => [
                'product_search' => [
                    'value' => [
                        'searches' => [
                            ['collection' => 'products', 'q' => '*', 'query_by' => 'name'],
                        ],
                    ],
                ],
            ],
            'stemming_dictionaries' => [
                'fr' => [
                    'words' => [
                        ['word' => 'chaussures', 'root' => 'chaussure'],
                    ],
                ],
            ],
            'analytics_rules' => [
                'popular_products' => [
                    'type' => 'popular_queries',
                    'params' => ['source' => ['collections' => ['products']]],
                ],
            ],
            'nl_search_models' => [
                'products-nl' => [
                    'model_name' => 'openai/gpt-4o-mini',
                    'api_key' => 'secret',
                ],
            ],
            'conversation_models' => [
                'products-chat' => [
                    'model_name' => 'openai/gpt-4o-mini',
                    'api_key' => 'secret',
                ],
            ],
        ]);

        $this->assertSame(['large', 'big', 'huge'], $result['synonym_sets']['products']['items']['size']['synonyms']);
        $this->assertSame('primary_color', $result['synonym_sets']['products']['items']['color']['root']);
        $this->assertSame('iphone', $result['curation_sets']['products']['items']['promote-iphone']['rule']['query']);
        $this->assertSame('products', $result['presets']['product_search']['value']['searches'][0]['collection']);
        $this->assertSame('chaussure', $result['stemming_dictionaries']['fr']['words'][0]['root']);
        $this->assertSame('popular_queries', $result['analytics_rules']['popular_products']['type']);
        $this->assertSame('openai/gpt-4o-mini', $result['nl_search_models']['products-nl']['model_name']);
        $this->assertSame('openai/gpt-4o-mini', $result['conversation_models']['products-chat']['model_name']);
    }
}
