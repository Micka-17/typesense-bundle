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

                ->arrayNode('llm')
                    ->addDefaultsIfNotSet()
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('api_key')->defaultNull()->end()
                        ->scalarNode('model')->defaultNull()->end()
                        ->integerNode('timeout')->defaultValue(30)->info("Délai d'attente en secondes pour la requête HTTP.")->end()
                        ->integerNode('max_retries')->defaultValue(3)->info("Nombre maximum de tentatives en cas d'échec.")->end()
                        ->scalarNode('endpoint')
                            ->info("L'URL complète de l'API à appeler, ex: 'https://api.openai.com/v1/embeddings'")
                            ->isRequired()
                        ->end()
                        ->arrayNode('payload')
                            ->info("Structure du JSON à envoyer. Utilisez {{text}} comme placeholder.")
                            ->isRequired()
                            ->children()
                                ->scalarNode('prompt')->defaultValue('{{text}}')->end()
                                ->scalarNode('model')->defaultValue('%llm.model%')->end()
                            ->end()
                        ->end()
                        ->scalarNode('response_path')
                            ->info("Chemin pour trouver l'embedding dans la réponse JSON, ex: 'data[0].embedding'")
                            ->defaultValue('data[0].embedding')
                        ->end()
                    ->end()
                ->end()

            ->end();

        return $treeBuilder;
    }
}
