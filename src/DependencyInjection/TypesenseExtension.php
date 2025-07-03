<?php

namespace Micka17\TypesenseBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class TypesenseExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('typesense.api_key', $config['api_key']);
        
        $container->setParameter('typesense.cluster.enabled', $config['cluster']['enabled']);
        $container->setParameter('typesense.cluster.read_preference', $config['cluster']['read_preference']);
        $container->setParameter('typesense.cluster.consistency_level', $config['cluster']['consistency_level']);
        $container->setParameter('typesense.cluster.nodes', $config['cluster']['nodes']);
        
        if (!$config['cluster']['enabled']) {
            $container->setParameter('typesense.nodes', [
                [
                    'host' => $config['cluster']['nodes'][0]['host'],
                    'port' => $config['cluster']['nodes'][0]['port'],
                    'protocol' => $config['cluster']['nodes'][0]['protocol'],
                    'role' => $config['cluster']['nodes'][0]['role']
                ]
            ]);
        }

        $container->setParameter('typesense.auto_update.enabled', $config['auto_update']);
        $container->setParameter('typesense.indexable_entities', $config['indexable_entities']);
        
        $container->setParameter('typesense.error_tracking.enabled', $config['error_tracking']['enabled']);
        $container->setParameter('typesense.error_tracking.log_level', $config['error_tracking']['log_level']);
        $container->setParameter('typesense.error_tracking.track_node_errors', $config['error_tracking']['track_node_errors']);
        $container->setParameter('typesense.error_tracking.node_error_fields', $config['error_tracking']['node_error_fields']);

        $container->setParameter('typesense.synonyms', $config['synonyms']);

        $this->addTwigNamespace($container);
    }

    private function addTwigNamespace(ContainerBuilder $container): void
    {
        if (!class_exists('\Symfony\Bundle\TwigBundle\TwigBundle')) {
            return;
        }

        $container->prependExtensionConfig('twig', [
            'paths' => [
                __DIR__.'/../../templates' => 'Micka17Typesense',
            ],
        ]);
    }
}