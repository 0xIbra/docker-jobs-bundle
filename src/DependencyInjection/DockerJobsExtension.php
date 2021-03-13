<?php

namespace Polkovnik\DockerJobsBundle\DependencyInjection;

use Polkovnik\DockerJobsBundle\Entity\BaseJob;
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
        $loader->load('services.yaml');
        $loader->load('console.yaml');

        if (empty($config['class']['job'])) {
            throw new InvalidConfigurationException('"docker_jobs.class.job" parameter must be defined.');
        }

        $container->setParameter('docker_jobs.docker_unix_socket_path', $config['docker_unix_socket_path']);
        $container->setParameter('docker_jobs.docker_base_uri', $config['docker_base_uri']);
        $container->setParameter('docker_jobs.docker_image', $config['docker_image']);
        $container->setParameter('docker_jobs.docker_working_dir', $config['docker_working_dir']);
        $container->setParameter('docker_jobs.class.job', $config['class']['job']);
        $container->setParameter('docker_jobs.runtime.concurrency_limit', $config['runtime']['concurrency_limit']);

        $this->addAnnotatedClassesToCompile([
            '**Polkovnik\\DockerJobsBundle\\Controller\\'
        ]);
    }
}
