<?php

namespace IterativeCode\DockerJobsBundle\Event;

use IterativeCode\DockerJobsBundle\Entity\BaseJob;
use Symfony\Contracts\EventDispatcher\Event;

class JobSubmittedEvent extends Event
{
    public const CODE = 'iterative_code.docker_jobs.events.job.submitted';

    /** @var BaseJob */
    protected $job;

    /**
     * @return BaseJob
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * @param BaseJob $job
     *
     * @return $this
     */
    public function setJob($job)
    {
        $this->job = $job;

        return $this;
    }

    public function getCode()
    {
        return self::CODE;
    }
}
