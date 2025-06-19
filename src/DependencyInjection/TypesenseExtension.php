<?php
// src/TypesenseBundle/DependencyInjection/TypesenseExtension.php

namespace Micka17\TypesenseBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class TypesenseExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('typesense.api_key', $config['api_key']);
        $container->setParameter('typesense.nodes', $config['nodes']);
        $container->setParameter('typesense.auto_update.enabled', $config['auto_update']);
        $container->setParameter('typesense.indexable_entities', $config['indexable_entities']);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');
    }
}