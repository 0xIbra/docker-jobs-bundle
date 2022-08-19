<?php

namespace IterativeCode\DockerJobsBundle\Service;

use IterativeCode\Component\DockerClient\DockerClient;
use IterativeCode\DockerJobsBundle\Entity\BaseJob;
use IterativeCode\DockerJobsBundle\Manager\JobManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DockerService
{
    const ORCHESTRATION_DOCKER_LABEL = 'iterative_code_docker_jobs';
    const DOCKER_JOB_IDENTIFYING_ENV = 'IS_DOCKER_JOB';

    /** @var ContainerInterface */
    private $container;

    /** @var DockerClient */
    private $docker;

    /** @var string */
    private $dockerImage;

    /** @var string */
    private $dockerWorkingDir;

    /** @var JobManager */
    private $jobManager;

    public function __construct(ContainerInterface $container, string $dockerApiEndpoint = null)
    {
        $this->container = $container;

        $options = [];

        if (null !== $dockerApiEndpoint) {
            $options['local_endpoint'] = $dockerApiEndpoint;
        }

        $isJob = getenv(self::DOCKER_JOB_IDENTIFYING_ENV);
        if (!empty($isJob)) {
            $this->docker = null;
        } else {
            $this->docker = new DockerClient($options);
        }

        $this->dockerImage = $container->getParameter('docker_jobs.docker.default_image_id');
        $this->dockerWorkingDir = $container->getParameter('docker_jobs.docker.container_working_dir');
        $this->jobManager = $container->get('iterative_code.docker_jobs.manager.job');
    }

    public function getClient()
    {
        return $this->docker;
    }

    public function getContainerUsage($id)
    {
        try {
            $stats = $this->docker->getContainerStats($id);
            $usage = [];

            if (!empty($stats['cpu_stats']['cpu_usage']['total_usage']) &&
                !empty($stats['precpu_stats']['cpu_usage']['total_usage']) &&
                !empty($stats['cpu_stats']['system_cpu_usage']) && $stats['precpu_stats']['system_cpu_usage']) {

                $cpuDelta = $stats['cpu_stats']['cpu_usage']['total_usage'] - $stats['precpu_stats']['cpu_usage']['total_usage'];
                $systemDelta = $stats['cpu_stats']['system_cpu_usage'] - $stats['precpu_stats']['system_cpu_usage'];
                $cpuUsage = ($cpuDelta / $systemDelta) * $stats['cpu_stats']['online_cpus'] * 100;

                $usage['cpu'] = $cpuUsage;
            }

            if (!empty($stats['memory_stats']['usage']) && !empty($stats['memory_stats']['stats']['cache']) &&
                !empty($stats["memory_stats"]["limit"])) {
                $usedMemory = $stats['memory_stats']['usage'] - $stats['memory_stats']['stats']['cache'];
                $availableMemory = $stats['memory_stats']['limit'];

                $memoryUsagePercent = ($usedMemory / $availableMemory) * 100.0;

                $usage['memoryPercent'] = $memoryUsagePercent;
                $usage['usedMemory'] = $usedMemory;
                $usage['availableMemory'] = $availableMemory;
            }

            return $usage;
        } catch (\Exception $exception) {}

        return null;
    }

    public function imageExists($name)
    {
        try {
            $response = $this->docker->inspectImage($name);

            return !empty($response);
        } catch (\Exception $exception) {}

        return false;
    }

    public function stopJob(BaseJob $job, $deleteContainer = false)
    {
        try {
            $containerId = $job->getDockerContainerId();
            $stop = $this->docker->stopContainer($containerId);
            $logs = $this->docker->getContainerLogs($containerId);
            $job->setOutput($logs);

            if ($stop === true) {
                if ($deleteContainer === true) {
                    $deleted = $this->docker->deleteContainer($containerId);
                    if ($deleted) {
                        return true;
                    }
                }

                return true;
            }

        } catch (\Exception $exception) {}

        return false;
    }

    public function getDockerImages()
    {
        try {
            return $this->docker->listImages();
        } catch (\Exception $e) {}

        return null;
    }

    public function getJobConfig(BaseJob $job)
    {
        $image = $this->dockerImage;
        if (!empty($job->getDockerImageId())) {
            $image = $job->getDockerImageId();
        }

        $command = $job->getCommand();
        $config = [
            'Image' => $image,
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
            'Env' => [sprintf('%s=true', self::DOCKER_JOB_IDENTIFYING_ENV), 'TZ=Europe/Paris']
        ];

        return $config;
    }

    /**
     * @param $dateString
     *
     * @return \DateTime|false
     */
    public function getDateTime($dateString)
    {
        $diff = $this->container->getParameter('docker_jobs.docker.time_difference');
        $date = \DateTime::createFromFormat('Y-m-d\\TH:i:s.u+', $dateString);
        $date->modify($diff);

        return $date;
    }
}
