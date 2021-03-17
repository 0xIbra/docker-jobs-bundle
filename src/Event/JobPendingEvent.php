<?php

namespace Polkovnik\DockerJobsBundle\Event;

use Polkovnik\DockerJobsBundle\Entity\BaseJob;
use Symfony\Component\EventDispatcher\Event;

class JobPendingEvent extends Event
{
    public const CODE = 'polkovnik.docker_jobs.events.job.pending';

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
