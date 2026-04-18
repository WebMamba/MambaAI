<?php

namespace MambaAi\Version_2\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('mamba_ai');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('agents_dir')
                    ->defaultValue('%kernel.project_dir%/agents')
                ->end()
                ->scalarNode('default_platform')
                    ->defaultValue('anthropic.platform')
                ->end()
                ->scalarNode('default_model')
                    ->defaultValue('claude-3-haiku-20240307')
                ->end()
                ->arrayNode('slack')
                    ->children()
                        ->scalarNode('bot_token')->defaultValue('')->end()
                        ->scalarNode('signing_secret')->defaultValue('')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
