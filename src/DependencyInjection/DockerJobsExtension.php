<?php

namespace IterativeCode\DockerJobsBundle\DependencyInjection;

use IterativeCode\DockerJobsBundle\Entity\BaseJob;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class DockerJobsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('console.yml');

        if (empty($config['class']['job'])) {
            throw new InvalidConfigurationException('"docker_jobs.class.job" parameter must be defined.');
        }

        if (empty($config['docker']['default_image_id'])) {
            throw new InvalidConfigurationException('"docker_jobs.docker.default_image_id" parameter must be defined.');
        }

        if (empty($config['docker']['container_working_dir'])) {
            throw new InvalidConfigurationException('"docker_jobs.docker.container_working_dir" parameter must be defined.');
        }

        $container->setParameter('docker_jobs.class.job', $config['class']['job']);
        $container->setParameter('docker_jobs.runtime.concurrency_limit', $config['runtime']['concurrency_limit']);
        $container->setParameter('docker_jobs.docker.docker_api_endpoint', $config['docker']['docker_api_endpoint']);
        $container->setParameter('docker_jobs.docker.default_image_id', $config['docker']['default_image_id']);
        $container->setParameter('docker_jobs.docker.container_working_dir', $config['docker']['container_working_dir']);
        $container->setParameter('docker_jobs.docker.time_difference', $config['docker']['time_difference']);

        $this->addAnnotatedClassesToCompile([
            'IterativeCode\\DockerJobsBundle\\Controller\\MonitoringController'
        ]);
    }
}
