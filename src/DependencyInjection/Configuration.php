<?php

namespace Polkovnik\DockerJobsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('polkovnik_docker_jobs');

        $rootNode
            ->children()
                ->scalarNode('docker_unix_socket_path')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('docker_base_uri')
                    ->defaultNull()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

}
