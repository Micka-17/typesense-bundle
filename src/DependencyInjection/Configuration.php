<?php
// src/TypesenseBundle/DependencyInjection/Configuration.php

namespace Micka17\TypesenseBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('typesense');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('nodes')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('host')->isRequired()->end()
                            ->integerNode('port')->defaultValue(8108)->end()
                            ->scalarNode('protocol')->defaultValue('http')->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('api_key')->isRequired()->end()
                ->booleanNode('auto_update')->defaultTrue()->end()
                ->arrayNode('indexable_entities')
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}