<?php

namespace IterativeCode\DockerJobsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass(repositoryClass="IterativeCode\DockerJobsBundle\Entity\Repository\BaseJobRepository")
 */
class BaseJob
{
    const STATE_NEW = 'new';
    const STATE_PENDING = 'pending';
    const STATE_CANCELED = 'canceled';
    const STATE_RUNNING = 'running';
    const STATE_FINISHED = 'finished';
    const STATE_FAILED = 'failed';
    const STATE_STOPPED = 'stopped';

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
     * @var boolean
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $checked;

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
    protected $startedAtFallback;

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
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $stoppedAt;

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
     * @var float
     *
     * @ORM\Column(type="float", nullable=true)
     */
    protected $cpuUsage;

    /**
     * @var int
     *
     * @ORM\Column(type="float", nullable=true)
     */
    protected $memoryUsage;

    /**
     * @var array
     *
     * @ORM\Column(type="array", nullable=true)
     */
    protected $environmentVariables;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $exitCode;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected $errorMessage;

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

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $dockerImageId;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $dockerContainerId;

    public function __construct()
    {
        $this->checked = false;
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
            self::STATE_STOPPED,
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
    public function setState($state)
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
    public function setQueue($queue)
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
    public function setWorkerName($workerName)
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
    public function setCommand($command)
    {
        $this->command = $command;

        return $this;
    }

    /**
     * @return bool
     */
    public function isChecked()
    {
        return $this->checked;
    }

    /**
     * @param bool $checked
     *
     * @return self
     */
    public function setChecked($checked)
    {
        $this->checked = $checked;

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
    public function setCreatedAt($createdAt)
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
    public function setStartedAt($startedAt)
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getStartedAtFallback()
    {
        return $this->startedAtFallback;
    }

    /**
     * @param \DateTime $startedAtFallback
     *
     * @return self
     */
    public function setStartedAtFallback($startedAtFallback)
    {
        $this->startedAtFallback = $startedAtFallback;

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
    public function setCheckedAt($checkedAt)
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
    public function setExecutingAfter($executingAfter)
    {
        $this->executingAfter = $executingAfter;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getStoppedAt()
    {
        return $this->stoppedAt;
    }

    /**
     * @param \DateTime $stoppedAt
     *
     * @return self
     */
    public function setStoppedAt($stoppedAt)
    {
        $this->stoppedAt = $stoppedAt;

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
    public function setOutput($output)
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
    public function setErrorOutput($errorOutput)
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
    public function setStackTrace($stackTrace)
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
    public function setRuntime($runtime)
    {
        $this->runtime = $runtime;

        return $this;
    }

    /**
     * @return float
     */
    public function getCpuUsage()
    {
        return $this->cpuUsage;
    }

    /**
     * @param float $cpuUsage
     *
     * @return self
     */
    public function setCpuUsage($cpuUsage)
    {
        $this->cpuUsage = $cpuUsage;

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
    public function setMemoryUsage($memoryUsage)
    {
        $this->memoryUsage = $memoryUsage;

        return $this;
    }

    /**
     * @return array
     */
    public function getEnvironmentVariables()
    {
        return $this->environmentVariables;
    }

    /**
     * @param array $environmentVariables
     *
     * @return self
     */
    public function setEnvironmentVariables($environmentVariables)
    {
        $this->environmentVariables = $environmentVariables;

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
    public function setExitCode($exitCode)
    {
        $this->exitCode = $exitCode;

        return $this;
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @param string $errorMessage
     *
     * @return self
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;
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
    public function setMaxRuntime($maxRuntime)
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
    public function setMaxRetries($maxRetries)
    {
        $this->maxRetries = $maxRetries;

        return $this;
    }

    /**
     * @return string
     */
    public function getDockerImageId()
    {
        return $this->dockerImageId;
    }

    /**
     * @param string $dockerImageId
     *
     * @return self
     */
    public function setDockerImageId($dockerImageId)
    {
        $this->dockerImageId = $dockerImageId;

        return $this;
    }

    /**
     * @return string
     */
    public function getDockerContainerId()
    {
        return $this->dockerContainerId;
    }

    /**
     * @param string $dockerContainerId
     *
     * @return self
     */
    public function setDockerContainerId($dockerContainerId)
    {
        $this->dockerContainerId = $dockerContainerId;

        return $this;
    }
}
