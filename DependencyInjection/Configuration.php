<?php

namespace Targus\ObjectFetcher\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('targus_object_fetcher');

        $rootNode
            ->children()
                ->arrayNode('defaults')
                    ->children()
                        ->scalarNode('required')
                            ->defaultValue(false)
                        ->end()
                        ->scalarNode('profile')
                            ->defaultValue('common')
                        ->end()
                        ->scalarNode('dateTimeFormat')
                            ->defaultValue(DATE_W3C)
                        ->end()
                        ->booleanNode('nullable')
                            ->defaultValue(true)
                        ->end()
                    ->end()
                ->end();

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        return $treeBuilder;
    }
}
