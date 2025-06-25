<?php

namespace Micka17\TypesenseBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('typesense');

        $rootNode = $treeBuilder->getRootNode();
        
        $rootNode
            ->children()
                ->scalarNode('api_key')->isRequired()->end()
                ->booleanNode('auto_update')->defaultTrue()->end()
                ->arrayNode('error_tracking')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('log_level')
                            ->defaultValue('error')
                            ->validate()
                            ->ifNotInArray(['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'])
                            ->thenInvalid('Le niveau de log doit être un niveau valide')
                            ->end()
                        ->end()
                        ->booleanNode('track_node_errors')->defaultTrue()->end()
                        ->arrayNode('node_error_fields')
                            ->prototype('scalar')
                                ->validate()
                                ->ifNotInArray(['host', 'port', 'protocol', 'role', 'error_message', 'error_code', 'timestamp'])
                                ->thenInvalid('Champ de suivi invalide')
                                ->end()
                            ->end()
                            ->defaultValue(['host', 'port', 'error_message', 'error_code', 'timestamp'])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('indexable_entities')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('cluster')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('read_preference')
                            ->defaultValue('master_only')
                            ->validate()
                            ->ifNotInArray(['master_only', 'replica_only', 'nearest'])
                            ->thenInvalid('La préférence de lecture doit être master_only, replica_only ou nearest')
                            ->end()
                        ->end()
                        ->integerNode('consistency_level')
                            ->defaultValue(1)
                            ->min(1)
                            ->max(3)
                        ->end()
                        ->arrayNode('nodes')
                            ->requiresAtLeastOneElement()
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('host')->isRequired()->end()
                                    ->integerNode('port')->defaultValue(8108)->end()
                                    ->scalarNode('protocol')->defaultValue('http')->end()
                                    ->scalarNode('role')
                                        ->defaultValue('master')
                                        ->validate()
                                        ->ifNotInArray(['master', 'replica'])
                                        ->thenInvalid('Le rôle du nœud doit être master ou replica')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}