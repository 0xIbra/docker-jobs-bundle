<?php

namespace Polkovnik\DockerJobsBundle\Command;

use App\Entity\Job;
use Doctrine\ORM\EntityManagerInterface;
use Polkovnik\DockerClient;
use Polkovnik\DockerJobsBundle\Entity\BaseJob;
use Polkovnik\DockerJobsBundle\Entity\Repository\BaseJobRepository;
use Polkovnik\DockerJobsBundle\Exception\DockerImageNotFoundException;
use Polkovnik\DockerJobsBundle\Service\DockerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class JobOrchestrationCommand extends Command
{

    /** @var ContainerInterface */
    private $container;

    /** @var DockerService */
    private $docker;

    /** @var EntityManagerInterface */
    private $em;

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

    /** @var OutputInterface */
    private $output;

    public function __construct(ContainerInterface $container, DockerService $docker, $name = null)
    {
        $this->container = $container;
        $this->docker = $docker;
        $this->em = $container->get('doctrine')->getManager();
        $this->jobRepository = $this->em->getRepository($this->container->getParameter('docker_jobs.class.job'));

        $this->concurrency = $this->container->getParameter('docker_jobs.runtime.concurrency_limit');

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('polkovnik:docker-jobs:orchestrate')
            ->setDescription('Coming soon...')
            ->addOption('--queue', null, InputOption::VALUE_OPTIONAL, 'Queue to process.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->checkRequirements();

        try {
            $this->safeExecute($input, $output);
        } catch (\Exception $exception) {
            dump($exception->getMessage());
            dd($exception->getTraceAsString());
        }
    }

    protected function safeExecute(InputInterface $input, OutputInterface $output)
    {
        $firstTime = true;

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
                $container = $this->docker->getClient()->inspectContainer($containerId);
                $jobId = (int) $container['Config']['Labels']['job_id'];

                /** @var BaseJob $job */
                $job = $this->jobRepository->find($jobId);

                $containerLogs = $this->docker->getClient()->getContainerLogs($containerId);
                $errorLogs = $this->docker->getClient()->getContainerLogs($containerId, 'error');
                $job
                    ->setOutput((string) $containerLogs)
                    ->setErrorOutput((string) $errorLogs)
                    ->setState(BaseJob::STATE_RUNNING)
                ;
                $this->em->persist($job);
            }

            foreach($this->stoppedContainers as $containerId) {
                $container = $this->docker->getClient()->inspectContainer($containerId);
                $jobId = (int) $container['Config']['Labels']['job_id'];

                /** @var BaseJob $job */
                $job = $this->jobRepository->find($jobId);

                if (!empty($container['State']['StartedAt']))

                $state = BaseJob::STATE_FINISHED;
                if ($container['State']['ExitCode'] !== 0) {
                    $state = BaseJob::STATE_FAILED;
                    $job
                        ->setErrorMessage($container['State']['Error'])
                        ->setExitCode($container['State']['ExitCode'])
                    ;
                }

                $containerLogs = $this->docker->getClient()->getContainerLogs($containerId);
                $errorLogs = $this->docker->getClient()->getContainerLogs($containerId, 'error');
                $job
                    ->setState($state)
                    ->setOutput((string) $containerLogs)
                    ->setErrorOutput((string) $errorLogs)
                ;

                $deletion = $this->docker->getClient()->deleteContainer($containerId);

                $exitCode = $container['State']['ExitCode'];
                if ($exitCode === 0) {
                    $this->log('success', 'job finished with success', $job);
                } else {
                    $this->log('error', 'job exited with code: ' . $exitCode, $job);
                }

                $this->em->persist($job);
                unset($this->stoppedContainers[$containerId]);
                unset($this->runningContainerCount[$containerId]);

                $this->updateCounters();
            }

            $this->em->flush();

            sleep(1);
            gc_collect_cycles();
        }
    }

    protected function checkRequirements()
    {
        $this->docker->getClient()->info();

        $dockerImage = $this->container->getParameter('docker_jobs.docker_image_id');
        if (!$this->docker->imageExists($dockerImage)) {
            throw new DockerImageNotFoundException(sprintf('"%s" docker image does not exist.', $dockerImage));
        }
    }

    protected function runJob(BaseJob $job)
    {
        $job
            ->setStartedAt(new \DateTime())
            ->setWorkerName(gethostname())
            ->setState(BaseJob::STATE_PENDING)
        ;

        $dockerConfig = $this->docker->getJobConfig($job);

        $command = implode(' ', $dockerConfig['Cmd']);
        $id = $this->docker->getClient()->runContainer(md5($command . '-' . $job->getId()), $dockerConfig);
        if ($id === false) {
            throw new \Exception('could not run job.');
        }

        $this->log('info', 'new job starting.', $job);

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

        $containers = $this->docker->getClient()->listContainers(DockerService::ORCHESTRATION_DOCKER_LABEL);

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
        if ($job !== null) {
            $text = sprintf('[%s][job #%s] %s', $level, $job->getId(), $text);
        } else {
            $text = sprintf('[%s] %s', $level, $text);
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
