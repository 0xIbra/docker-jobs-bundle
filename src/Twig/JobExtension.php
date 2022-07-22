<?php

namespace IterativeCode\DockerJobsBundle\Twig;

use IterativeCode\DockerJobsBundle\Entity\BaseJob;
use IterativeCode\DockerJobsBundle\Manager\JobManager;
use IterativeCode\DockerJobsBundle\Service\DockerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class JobExtension extends AbstractExtension
{
    /** @var ContainerInterface */
    private $container;

    /** @var DockerService */
    private $docker;

    /** @var JobManager */
    private $jobManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container    = $container;
        $this->docker       = $container->get('iterative_code.docker_jobs.service.docker');
        $this->jobManager   = $container->get('iterative_code.docker_jobs.manager.job');
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('get_job_runtime', [$this, 'getRuntime']),
            new TwigFunction('get_state_from_filters', [$this, 'getStateFromFilters']),
        ];
    }

    public function getRuntime(BaseJob  $job)
    {
        if ($job->getStartedAt() !== null) {
            $startedAt = $job->getStartedAt();
            $stoppedAt = $job->getStoppedAt();
            if ($stoppedAt !== null) {
                $diff = (int) ceil($stoppedAt->getTimestamp() - $startedAt->getTimestamp());

                return $diff;
            }

            if ($job->getState() === BaseJob::STATE_RUNNING) {
                $now = new \DateTime();
                $diff = (int) ceil($now->getTimestamp() - $startedAt->getTimestamp());

                return gmdate('H:i:s', $diff);
            }
        }

        return null;
    }

    public function getStateFromFilters($filtersString)
    {
        try {
            $filters = json_decode($filtersString, true);
            if (!empty($filters['state'])) {
                $state = $filters['state'];
                if (in_array($state, BaseJob::getStates())) {
                    return $state;
                }
            }
        } catch (\Exception $e) {}

        return null;
    }
}
