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

            foreach ($this->runningContainers as $container) {
                $jobId = (int) $container['Labels']['job_id'];
                $containerId = $container['Id'];
                $job = $this->jobRepository->find($jobId);

                $containerLogs = $this->docker->getClient()->getContainerLogs($containerId);
                $job
                    ->setOutput($containerLogs)
                    ->setState(BaseJob::STATE_RUNNING)
                ;
                $this->em->persist($job);
            }

            foreach($this->stoppedContainers as $container) {
                $containerId = $container['Id'];
                $container = $this->docker->getClient()->inspectContainer($containerId);
                $jobId = (int) $container['Labels']['job_id'];

                /** @var BaseJob $job */
                $job = $this->jobRepository->find($jobId);

                $state = BaseJob::STATE_FINISHED;
                if ($container['State']['ExitCode'] !== 0) {
                    $state = BaseJob::STATE_FAILED;
                    $job
                        ->setErrorOutput($container['State']['Error'])
                        ->setExitCode($container['State']['ExitCode'])
                    ;
                }

                $containerLogs = $this->docker->getClient()->getContainerLogs($containerId);
                $job
                    ->setState($state)
                    ->setOutput($containerLogs)
                ;

                $this->docker->getClient()->

                $this->em->persist($job);
                unset($this->stoppedContainers[$containerId]);
            }

            $this->em->flush();

            sleep(3);
            gc_collect_cycles();
        }
    }

    protected function checkRequirements()
    {
        $this->docker->getClient()->info();

        $dockerImage = $this->container->getParameter('docker_jobs.docker_image');
        if (!$this->docker->imageExists($dockerImage)) {
            throw new DockerImageNotFoundException(sprintf('"%s" docker image does not exist.', $dockerImage));
        }
    }

    protected function runJob(BaseJob $job)
    {
        $job
            ->startedAt(new \DateTime())
            ->setWorkerName(gethostname())
            ->setState(BaseJob::STATE_PENDING)
        ;

        $dockerConfig = $this->docker->getJobConfig($job);

        $command = implode(' ', $dockerConfig['Cmd']);
        $id = $this->docker->getClient()->runContainer(md5($command), $dockerConfig);
        if ($id === false) {
            throw new \Exception('could not run job.');
        }

        $this->runningContainers[] = $this->docker->getClient()->inspectContainer($id);
    }

    protected function retrieveRunnableJobs($limit)
    {
        return $this->jobRepository->findRunnableJobs($this->queue, $limit);
    }

    protected function updateContainers()
    {
        $containers = $this->docker->getClient()->listContainers(DockerService::ORCHESTRATION_DOCKER_LABEL);

        foreach ($containers as $container) {
            $state = $container['State'];
            if (!in_array($state, ['running', 'exited'])) {
                $text = sprintf('<comment>Unhandled container state. State: %s</comment>', $state);
                $this->output->writeln($text);
            }

            $containerId = $container['Id'];
            if ($state === 'running') {
                $this->runningContainers[$containerId] = $container;
            } else if ($state === 'exited') {
                $this->stoppedContainers[$containerId] = $container;
            }

            $this->updateCounters();
        }
    }

    protected function updateCounters()
    {
        $this->runningContainerCount = count($this->runningContainers);
        $this->stoppedContainerCount = count($this->stoppedContainers);
    }
}
