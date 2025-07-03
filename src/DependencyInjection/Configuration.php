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
                            ->scalarPrototype()
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
                            ->defaultValue('leader_only')
                            ->validate()
                                ->ifNotInArray(['leader_only', 'follower_only', 'nearest'])
                                ->thenInvalid('La préférence de lecture doit être leader_only, follower_only ou nearest')
                            ->end()
                        ->end()
                        ->integerNode('consistency_level')
                            ->defaultValue(1)
                            ->min(1)
                        ->end()
                        ->arrayNode('nodes')
                            ->requiresAtLeastOneElement()
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('host')->isRequired()->end()
                                    ->integerNode('port')->defaultValue(8108)->end()
                                    ->scalarNode('protocol')->defaultValue('http')->end()
                                    ->scalarNode('role')
                                        ->defaultValue('leader')
                                        ->validate()
                                            ->ifNotInArray(['leader', 'follower'])
                                            ->thenInvalid('Le rôle du nœud doit être leader ou follower')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('synonyms')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('id')->isRequired()->end()
                            ->scalarNode('root')->end()
                            ->arrayNode('synonyms')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()

            ->end();

        return $treeBuilder;
    }
}
