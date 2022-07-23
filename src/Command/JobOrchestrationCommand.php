<?php

namespace IterativeCode\DockerJobsBundle\Command;

use App\Entity\Job;
use Doctrine\ORM\EntityManagerInterface;
use IterativeCode\Component\DockerClient\Exception\ResourceBusyException;
use IterativeCode\Component\DockerClient\Exception\ResourceNotFound;
use IterativeCode\DockerJobsBundle\Entity\BaseJob;
use IterativeCode\DockerJobsBundle\Entity\Repository\BaseJobRepository;
use IterativeCode\DockerJobsBundle\Event\JobFailedEvent;
use IterativeCode\DockerJobsBundle\Event\JobFinishedEvent;
use IterativeCode\DockerJobsBundle\Event\JobRunningEvent;
use IterativeCode\DockerJobsBundle\Event\JobStoppedEvent;
use IterativeCode\DockerJobsBundle\Exception\DockerImageNotFoundException;
use IterativeCode\DockerJobsBundle\Service\DockerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class JobOrchestrationCommand extends Command
{

    /** @var ContainerInterface */
    private $container;

    /** @var DockerService */
    private $docker;

    /** @var EntityManagerInterface */
    private $em;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var BaseJobRepository */
    private $jobRepository;

    /** @var string */
    private $queue = 'default';

    /** @var integer */
    private $concurrency;

    /** @var int */
    private $runningContainerCount = 0;

    /** @var int */
    private $stoppedContainerCount = 0;

    /** @var array */
    private $runningContainers = [];

    /** @var array */
    private $stoppedContainers = [];

    /** @var array */
    private $logPointers;

    /** @var OutputInterface */
    private $output;

    public function __construct(ContainerInterface $container, DockerService $docker, $name = null)
    {
        $this->container = $container;
        $this->docker = $docker;
        $this->em = $container->get('doctrine')->getManager();
        $this->eventDispatcher = $container->get('event_dispatcher');
        $this->jobRepository = $this->em->getRepository($this->container->getParameter('docker_jobs.class.job'));

        $this->concurrency = $this->container->getParameter('docker_jobs.runtime.concurrency_limit');

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('iterative_code:jobs:orchestrate')
            ->setDescription('Orchestrates Docker containers and handles them in their different stages.')
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'Queue to process.')
            ->addOption('update-logs-eager', null, InputOption::VALUE_OPTIONAL, 'When enabled, updates the logs of running jobs every few seconds, otherwise only updates at the end of the job.', true)
            ->addOption('concurrency', null, InputOption::VALUE_OPTIONAL, 'Specify how many jobs you want to run concurrently.', 4)
            ->addOption('interval', null, InputOption::VALUE_OPTIONAL, 'Specify the number of seconds to wait between each iteration.', 1)
            ->addOption('max-runtime', null, InputOption::VALUE_OPTIONAL, 'Specify the number of seconds the command should run for before exiting.', 900)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->checkRequirements();

//        try {
        $this->safeExecute($input, $output);
//        } catch (\Exception $exception) {
//            dump($exception->getMessage());
//            dd($exception->getTraceAsString());
//        }

        return 0;
    }

    protected function safeExecute(InputInterface $input, OutputInterface $output)
    {
        $startTime = new \DateTime();

        $firstTime = true;
        $inputOptions = $input->getOptions();
        $maxRuntime = (int) $inputOptions['max-runtime'];
        $eagerUpdateLogs = $inputOptions['update-logs-eager'];
        if (!empty($inputOptions['concurrency']) && $inputOptions['concurrency'] > 0) {
            $this->concurrency = (int) $inputOptions['concurrency'];
        }
        $interval = 1;
        if (!empty($inputOptions['interval'])) {
            $interval = (int) $inputOptions['interval'];
        }

        while (true) {

            if ($firstTime) {
                $this->updateContainers();
                $firstTime = false;
            }

            if ($this->runningContainerCount < $this->concurrency) {
                # If concurrency limit is not yet reached

                $availableSlots = $this->concurrency - $this->runningContainerCount;
                $jobs = $this->retrieveRunnableJobs($availableSlots);
                foreach ($jobs as $job) {
                    $this->runJob($job);
                    $this->em->persist($job);
                }
                $this->em->flush();
            }

            $this->updateContainers();

            foreach ($this->runningContainers as $containerId) {
                try {
                    $container = $this->docker->getClient()->inspectContainer($containerId);
                } catch (ResourceNotFound $e) {
                    $text = sprintf('cannot inspect container.' . PHP_EOL . 'no such container: %s', $containerId);
                    $this->log('error', $text);

                    continue;
                } catch (\Exception $e) {
                    $text = sprintf('cannot inspect container.'. PHP_EOL . 'reason: %s', $e->getMessage());
                    $this->log('error', $text);

                    continue;
                }

                $jobId = (int) $container['Config']['Labels']['job_id'];

                /** @var BaseJob $job */
                $job = $this->jobRepository->find($jobId);

                if ($eagerUpdateLogs === true) {
                    $containerLogs = $this->docker->getClient()->getContainerLogs($containerId);
                    $errorLogs = $this->docker->getClient()->getContainerLogs($containerId, 'error');

                    $job
                        ->setOutput((string) $containerLogs)
                        ->setErrorOutput((string) $errorLogs)
                    ;
                }

                try {
                    if ($job->getEnvironmentVariables() === null) {
                        $environmentVars = [];
                        $environmentVarsTemp = $container['Config']['Env'];
                        foreach ($environmentVarsTemp as $var) {
                            $var = explode('=', $var);

                            $environmentVars[] = ['name' => $var[0], 'value' => $var[1]];
                        }

                        if (!empty($environmentVarsTemp)) {
                            $job->setEnvironmentVariables($environmentVars);
                        }
                    }
                } catch (\Exception $e) {}

                if ($job->getState() !== BaseJob::STATE_RUNNING) {
                    $job->setState(BaseJob::STATE_RUNNING);
                }

                if ($job->getStartedAt() === null) {
                    if (!empty($container['State']['StartedAt'])) {
                        $date = $this->docker->getDateTime($container['State']['StartedAt']);
                        if ($date !== false && $date !== null) {
                            $job->setStartedAt($date);
                        }
                    }
                }

                $this->em->persist($job);
            }

            foreach($this->stoppedContainers as $containerId) {
                try {
                    $container = $this->docker->getClient()->inspectContainer($containerId);
                    $jobId = (int) $container['Config']['Labels']['job_id'];

                    /** @var BaseJob $job */
                    $job = $this->jobRepository->find($jobId);
                } catch (ResourceNotFound $e) {
                    $text = sprintf('cannot inspect stopped container.' . PHP_EOL . 'no such container: %s'. PHP_EOL . 'maybe it was deleted manually.', $containerId);
                    $this->log('error', $text);

                    continue;
                } catch (\Exception $e) {
                    $text = sprintf('cannot inspect stopped container.' . PHP_EOL . 'reason: %s', $e->getMessage());
                    $this->log('error', $text);

                    continue;
                }

                if (!empty($container['State']['FinishedAt'])) {
                    $date = $this->docker->getDateTime($container['State']['FinishedAt']);
                    if ($date !== false && $date !== null) {
                        $job->setStoppedAt($date);

                        $started = $job->getStartedAt();
                        if ($started === null) {
                            $started = $job->getStartedAtFallback();
                        }

                        $finished = $job->getStoppedAt();
                        $diff = $finished->getTimestamp() - $started->getTimestamp();
                        $job->setRuntime($diff);
                    }
                }

                $state = BaseJob::STATE_FINISHED;
                if ($container['State']['ExitCode'] !== 0) {
                    $state = BaseJob::STATE_FAILED;
                    if ($container['State']['ExitCode'] === 137) {
                        $this->em->refresh($job);
                        if ($job->getState() === BaseJob::STATE_STOPPED) {
                            $state = BaseJob::STATE_STOPPED;
                        }
                    }

                    $errorMessage = null;
                    if (!empty($container['State']['Error'])) {
                        $errorMessage = $container['State']['Error'];
                    }
                    $job
                        ->setErrorMessage($errorMessage)
                    ;
                }
                $job->setExitCode($container['State']['ExitCode']);

                try {
                    $containerLogs = $this->docker->getClient()->getContainerLogs($containerId);
                    $errorLogs = $this->docker->getClient()->getContainerLogs($containerId, 'error');

                    $job
                        ->setOutput((string) $containerLogs)
                        ->setErrorOutput((string) $errorLogs)
                    ;
                } catch (ResourceNotFound $e) {
                    $text = sprintf('cannot retrieve container logs.' . PHP_EOL . 'no such container: %s' . PHP_EOL . 'Maybe it was deleted manually.', $containerId);
                    $this->log('error', $text, $job);
                } catch (\Exception $e) {
                    $text = sprintf('cannot retrieve container logs.' . PHP_EOL . 'reason: %s', $e->getMessage());
                    $this->log('error', $text, $job);
                }

                $job
                    ->setState($state)
                ;

                try {
                    $deletion = $this->docker->getClient()->deleteContainer($containerId);
                } catch (ResourceNotFound $e) {
                    $text = sprintf('no such container: %s' . PHP_EOL . 'Maybe it was deleted manually.', $containerId);
                    $this->log('error', $text, $job);
                } catch (ResourceBusyException $e) {
                    $text = sprintf('container is busy: %s' . PHP_EOL . 'Cannot delete it right now.', $containerId);
                    $this->log('error', $text, $job);
                } catch (\Exception $e) {
                    $text = sprintf('Cannot delete container: %s' . PHP_EOL . 'Reason: %s', $containerId, $e->getMessage());
                    $this->log('error', $text, $job);
                }

                $exitCode = $container['State']['ExitCode'];
                if ($exitCode === 0) {
                    $this->log('success', 'job finished with success', $job);

                    $event = new JobFinishedEvent();
                    $event->setJob($job);
                } else if ($exitCode === 137 && $job->getState() === BaseJob::STATE_STOPPED) {
                    $this->log('warning', 'job stopped', $job);

                    $event = new JobStoppedEvent();
                    $event->setJob($job);
                } else {
                    $this->log('warning', 'job exited with code: ' . $exitCode, $job);

                    $event = new JobFailedEvent();
                    $event->setJob($job);
                }
                $this->eventDispatcher->dispatch($event, $event->getCode());

                $this->em->persist($job);
                unset($this->stoppedContainers[$containerId]);
                unset($this->runningContainerCount[$containerId]);

                $this->updateCounters();
            }

            $this->em->flush();

            sleep($interval);
            gc_collect_cycles();

            $now = new \DateTime();
            if (($now->getTimestamp() - $startTime->getTimestamp()) > $maxRuntime) {
                $this->log('info', sprintf('Runtime limit of %s seconds exceeded, exiting now.', $maxRuntime));
                exit(0);
            }
        }
    }

    protected function checkRequirements()
    {
        $this->docker->getClient()->info();

        $dockerImage = $this->container->getParameter('docker_jobs.docker.default_image_id');
        if (!$this->docker->imageExists($dockerImage)) {
            throw new DockerImageNotFoundException(sprintf('"%s" docker image does not exist.', $dockerImage));
        }
    }

    protected function runJob(BaseJob $job)
    {
        $job
            ->setWorkerName(gethostname())
            ->setState(BaseJob::STATE_PENDING)
            ->setStartedAtFallback(new \DateTime())
        ;

        $dockerConfig = $this->docker->getJobConfig($job);

        $command = implode(' ', $dockerConfig['Cmd']);
        $id = $this->docker->getClient()->runContainer(md5($command . '-' . $job->getId()), $dockerConfig);
        if ($id === false) {
            throw new \Exception('could not run job.');
        }
        $job->setDockerContainerId($id);

        $this->log('info', 'new job starting', $job);

        $event = new JobRunningEvent();
        $event->setJob($job);
        $this->eventDispatcher->dispatch($event, $event->getCode());

        $this->runningContainers[$id] = $id;
    }

    protected function retrieveRunnableJobs($limit)
    {
        return $this->jobRepository->findRunnableJobs($this->queue, $limit);
    }

    protected function updateContainers()
    {
        $this->runningContainers = [];
        $this->stoppedContainers = [];

        $options = [
            'all'   => true,
            'filters' => json_encode([
                'label' => [DockerService::ORCHESTRATION_DOCKER_LABEL],
            ]),
        ];
        $containers = $this->docker->getClient()->listContainers($options);

        foreach ($containers as $container) {
            $state = $container['State'];
            if (!in_array($state, ['running', 'exited'])) {
                $text = sprintf('<comment>Unhandled container state. State: %s</comment>', $state);
                $this->output->writeln($text);
            }

            $containerId = $container['Id'];
            if ($state === 'running') {
                $this->runningContainers[$containerId] = $containerId;
            } else if ($state === 'exited') {
                $this->stoppedContainers[$containerId] = $containerId;
            }

            $this->updateCounters();
        }
    }

    protected function updateCounters()
    {
        $this->runningContainerCount = count($this->runningContainers);
        $this->stoppedContainerCount = count($this->stoppedContainers);
    }

    private function log($level, $text, BaseJob $job = null)
    {
        $date = new \DateTime();
        $date = $date->format('Y-m-d\\TH:i:s');
        if ($job !== null) {
            $text = sprintf('[%s][%s][job #%s] %s', $date, $level, $job->getId(), $text);
        } else {
            $text = sprintf('[%s][%s] %s', $date, $level, $text);
        }

        if ($level === 'info') {
            $this->output->writeln($text);
        } else if ($level === 'error') {
            $this->output->writeln(sprintf('<error>%s</error>', $text));
        } else if ($level === 'success') {
            $this->output->writeln(sprintf('<info>%s</info>', $text));
        } else if ($level === 'warning') {
            $this->output->writeln(sprintf('<comment>%s</comment>', $text));
        }
    }
}
