<?php

namespace Polkovnik\DockerJobsBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;

class DockerService
{

    public function __construct(ContainerInterface $container, string $dockerUnixSocket, string $dockerBaseUri = null)
    {

    }
}
