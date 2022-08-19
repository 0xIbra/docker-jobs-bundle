<?php

namespace IterativeCode\DockerJobsBundle\DependencyInjection;

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
                ->arrayNode('docker')
                    ->children()
                        ->scalarNode('docker_api_endpoint')->defaultNull()->end()
                        ->scalarNode('default_image_id')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('container_working_dir')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('time_difference')->defaultValue('+1 hour')->end()
                    ->end()
                ->end()
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
