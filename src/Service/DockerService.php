<?php

namespace Polkovnik\DockerJobsBundle\Service;

use Polkovnik\DockerClient;
use Polkovnik\DockerJobsBundle\Entity\BaseJob;
use Polkovnik\DockerJobsBundle\Manager\JobManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DockerService
{
    const ORCHESTRATION_DOCKER_LABEL = 'polkovnik_docker_jobs';

    /** @var DockerClient */
    private $docker;

    /** @var string */
    private $dockerImage;

    /** @var JobManager */
    private $jobManager;

    public function __construct(ContainerInterface $container, string $dockerUnixSocket, string $dockerBaseUri = null)
    {
        $options = [
            'unix_socket' => $dockerUnixSocket,
        ];

        if (null !== $dockerBaseUri) {
            $options['docker_base_uri'] = $dockerBaseUri;
        }

        $this->docker = new DockerClient($options);
        $this->dockerImage = $container->getParameter('docker_jobs.docker_image');
        $this->jobManager = $container->get('polkovnik.docker_jobs.manager.job');
    }

    public function getClient()
    {
        return $this->docker;
    }

    public function imageExists($name)
    {
        try {
            $response = $this->docker->inspectImage($name);

            return !empty($response);
        } catch (\Exception $exception) {}

        return false;
    }

    public function getJobConfig(BaseJob $job)
    {
        $command = $this->jobManager->getCommand($job);
        $config = [
            'Image' => $this->dockerImage,
            'Cmd' => explode(' ', $command),
            'HostConfig' => [
                'LogConfig' => ['Type' => 'json-file'],
                'NetworkMode' => 'host'
            ],
            'Labels' => [
                self::ORCHESTRATION_DOCKER_LABEL => self::ORCHESTRATION_DOCKER_LABEL,
                'job_id' => (string) $job->getId(),
            ],
        ];
    }
}
