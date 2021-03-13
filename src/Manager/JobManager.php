<?php

namespace Polkovnik\DockerJobsBundle\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Polkovnik\DockerJobsBundle\Entity\BaseJob;
use Symfony\Component\DependencyInjection\ContainerInterface;

class JobManager
{
    /** @var ContainerInterface */
    private $container;

    /** @var EntityManagerInterface */
    private $em;

    /** @var string */
    private $class;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->em = $container->get('doctrine')->getManager();
        $this->class = $container->getParameter('docker_jobs.class.job');
    }

    /**
     * @param array $payload
     */
    public function createJob($payload)
    {
        if (empty($payload['command'])) {
            throw new \InvalidArgumentException('"command" attribute must be defined in the payload.');
        }

        $job = new $this->class();
        $job
            ->setCommand($payload['command'])
            ->setDockerImageId($payload['dockerImageId'])
        ;

        $this->em->persist($job);
        $this->em->flush();

        return $job;
    }

    public function getCommand(BaseJob $job)
    {
        $command = $job->getCommand();
        $commandImplode = [$this->getEntrypoint(), $command];

        return implode(' ', $commandImplode);
    }

    private function getEntrypoint()
    {
        try {
            $projectDir = $this->container->getParameter('docker_jobs.docker_working_dir');
            if (!empty($projectDir)) {
                $consolePath = $projectDir . '/bin/console';
                if (file_exists($consolePath)) {
                    return $consolePath;
                }
            }
        } catch (\Exception $e) {}

        $rootDir = $this->container->getParameter('docker_jobs.docker_working_dir');
        $rootDir = realpath($rootDir . '/../');
        $consolePath = $rootDir . '/bin/console';
        if (file_exists($consolePath)) {
            return $consolePath;
        }

        $consolePath = $rootDir . '/app/console';
        if (file_exists($consolePath)) {
            return $consolePath;
        }

        throw new \Exception('The symfony console file was not found.');
    }
}
