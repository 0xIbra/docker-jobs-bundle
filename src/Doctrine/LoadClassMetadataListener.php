<?php

namespace IterativeCode\DockerJobsBundle\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadClassMetadataListener implements EventSubscriber
{
    /** @var ContainerInterface */
    protected $container;

    /** @var string */
    protected $job;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $this->container;
        $this->job = $container->getParameter('docker_jobs.class.job');
    }

    public function getSubscribedEvents()
    {
        return [Events::loadClassMetadata];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        $metadata = $eventArgs->getClassMetadata();
        $class = new \ReflectionClass($metadata->getName());
        if ($this->job === $class->getName() && !$metadata->isMappedSuperclass) {
            $metadata->mapManyToMany([
                'targetEntity'  => $this->job,
                'fieldName'     => 'dependencies',
                'joinTable'     => [
                    'joinTable'
                ]

            ]);
        }
    }
}
