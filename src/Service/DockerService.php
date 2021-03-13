<?php

namespace Polkovnik\DockerJobsBundle\Service;

use Polkovnik\Component\DockerClient;
use Polkovnik\DockerJobsBundle\Entity\BaseJob;
use Polkovnik\DockerJobsBundle\Manager\JobManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DockerService
{
    const ORCHESTRATION_DOCKER_LABEL = 'polkovnik_docker_jobs';
    const DOCKER_JOB_IDENTIFYING_ENV = 'IS_DOCKER_JOB';

    /** @var DockerClient */
    private $docker;

    /** @var string */
    private $dockerImage;

    /** @var string */
    private $dockerWorkingDir;

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

        $isJob = getenv(self::DOCKER_JOB_IDENTIFYING_ENV);
        if (!empty($isJob)) {
            $this->docker = null;
        } else {
            $this->docker = new DockerClient($options);
        }

        $this->dockerImage = $container->getParameter('docker_jobs.docker_image');
        $this->dockerWorkingDir = $container->getParameter('docker_jobs.docker_working_dir');
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
        $command = $job->getCommand();
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
            'WorkingDir' => $this->dockerWorkingDir,
            'Env' => [sprintf('%s=true', self::DOCKER_JOB_IDENTIFYING_ENV)]
        ];

        return $config;
    }
}
