<?php

namespace Polkovnik\DockerJobsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass(repositoryClass="Polkovnik\DockerJobsBundle\Entity\Repository\BaseJobRepository")
 */
class BaseJob
{
    const STATE_NEW = 'new';
    const STATE_PENDING = 'pending';
    const STATE_CANCELED = 'canceled';
    const STATE_RUNNING = 'running';
    const STATE_FINISHED = 'finished';
    const STATE_FAILED = 'failed';

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $state = self::STATE_NEW;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $queue = 'default';

    /**
     * @var string
     *
     * @ORM\Column(type="smallint")
     */
    protected $priority = 0;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $workerName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $command;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $startedAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $checkedAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $executingAfter;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected $output;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected $errorOutput;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $stackTrace;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $runtime;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $memoryUsage;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $memoryUsageReal;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $exitCode;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxRuntime = 0;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxRetries = 0;

    public function __construct()
    {
        $this->args = [];
        $this->createdAt = new \DateTime();
    }

    public static function getStates()
    {
        return [
            self::STATE_NEW,
            self::STATE_PENDING,
            self::STATE_CANCELED,
            self::STATE_RUNNING,
            self::STATE_FINISHED,
            self::STATE_FAILED,
        ];
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return self
     */
    public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param string $state
     *
     * @return self
     */
    public function setState(string $state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param string $queue
     *
     * @return self
     */
    public function setQueue(string $queue)
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * @return string
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param string $priority
     *
     * @return self
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return string
     */
    public function getWorkerName()
    {
        return $this->workerName;
    }

    /**
     * @param string $workerName
     *
     * @return self
     */
    public function setWorkerName(string $workerName)
    {
        $this->workerName = $workerName;

        return $this;
    }

    /**
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param string $command
     *
     * @return self
     */
    public function setCommand(string $command)
    {
        $this->command = $command;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     *
     * @return self
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getStartedAt()
    {
        return $this->startedAt;
    }

    /**
     * @param \DateTime $startedAt
     *
     * @return self
     */
    public function setStartedAt(\DateTime $startedAt)
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCheckedAt()
    {
        return $this->checkedAt;
    }

    /**
     * @param \DateTime $checkedAt
     *
     * @return self
     */
    public function setCheckedAt(\DateTime $checkedAt)
    {
        $this->checkedAt = $checkedAt;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getExecutingAfter()
    {
        return $this->executingAfter;
    }

    /**
     * @param \DateTime $executingAfter
     *
     * @return self
     */
    public function setExecutingAfter(\DateTime $executingAfter)
    {
        $this->executingAfter = $executingAfter;

        return $this;
    }

    /**
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @param string $output
     *
     * @return self
     */
    public function setOutput(string $output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * @return string
     */
    public function getErrorOutput()
    {
        return $this->errorOutput;
    }

    /**
     * @param string $errorOutput
     *
     * @return self
     */
    public function setErrorOutput(string $errorOutput)
    {
        $this->errorOutput = $errorOutput;

        return $this;
    }

    /**
     * @return string
     */
    public function getStackTrace()
    {
        return $this->stackTrace;
    }

    /**
     * @param string $stackTrace
     *
     * @return self
     */
    public function setStackTrace(string $stackTrace)
    {
        $this->stackTrace = $stackTrace;

        return $this;
    }

    /**
     * @return int
     */
    public function getRuntime()
    {
        return $this->runtime;
    }

    /**
     * @param int $runtime
     *
     * @return self
     */
    public function setRuntime(int $runtime)
    {
        $this->runtime = $runtime;

        return $this;
    }

    /**
     * @return int
     */
    public function getMemoryUsage()
    {
        return $this->memoryUsage;
    }

    /**
     * @param int $memoryUsage
     *
     * @return self
     */
    public function setMemoryUsage(int $memoryUsage)
    {
        $this->memoryUsage = $memoryUsage;

        return $this;
    }

    /**
     * @return int
     */
    public function getMemoryUsageReal()
    {
        return $this->memoryUsageReal;
    }

    /**
     * @param int $memoryUsageReal
     *
     * @return self
     */
    public function setMemoryUsageReal(int $memoryUsageReal)
    {
        $this->memoryUsageReal = $memoryUsageReal;

        return $this;
    }

    /**
     * @return int
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }

    /**
     * @param int $exitCode
     *
     * @return self
     */
    public function setExitCode(int $exitCode)
    {
        $this->exitCode = $exitCode;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxRuntime()
    {
        return $this->maxRuntime;
    }

    /**
     * @param int $maxRuntime
     *
     * @return self
     */
    public function setMaxRuntime(int $maxRuntime)
    {
        $this->maxRuntime = $maxRuntime;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxRetries()
    {
        return $this->maxRetries;
    }

    /**
     * @param int $maxRetries
     *
     * @return self
     */
    public function setMaxRetries(int $maxRetries)
    {
        $this->maxRetries = $maxRetries;

        return $this;
    }
}
