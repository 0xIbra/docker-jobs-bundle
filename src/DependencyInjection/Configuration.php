<?php

namespace Polkovnik\DockerJobsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('docker_jobs');

        $rootNode
            ->children()
                ->arrayNode('class')
                    ->children()
                        ->scalarNode('job')->isRequired()->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->scalarNode('docker_unix_socket_path')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('docker_base_uri')->defaultNull()->end()
                ->scalarNode('docker_image')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('docker_working_dir')->isRequired()->cannotBeEmpty()->end()
                ->arrayNode('runtime')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('concurrency_limit')->defaultValue(4)->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

}
