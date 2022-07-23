<?php

namespace IterativeCode\DockerJobsBundle\Command;

use Doctrine\ORM\EntityNotFoundException;
use IterativeCode\Component\DockerClient\Exception\ResourceNotFound;
use IterativeCode\DockerJobsBundle\Entity\BaseJob;
use IterativeCode\DockerJobsBundle\Service\DockerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CleanOrphanJobsCommand extends Command
{
    /** @var ContainerInterface */
    private $container;

    /** @var DockerService */
    private $docker;

    public function __construct(ContainerInterface $container, DockerService $docker, $name = null)
    {
        $this->container = $container;
        $this->docker = $docker;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('iterative_code:jobs:clean')
            ->setDescription('Removes orphan jobs from the running state.')
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'Queue from which will be removed orphan jobs.')
            ->addOption('dry-run', null, InputOption::VALUE_OPTIONAL, 'Run without applying changes.', false)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dryRun = $input->getOption('dry-run');
        if ($dryRun !== false) {
            $dryRun = true;
        }
        $queue = $input->getOption('queue');

        $em = $this->container->get('doctrine')->getManager();
        $jobRepository = $em->getRepository($this->container->getParameter('docker_jobs.class.job'));

        $filters = ['state' => BaseJob::STATE_RUNNING];
        if (!empty($queue)) {
            $filters['queue'] = $queue;
        }

        $jobs = $jobRepository->findBy($filters);
        foreach ($jobs as $job) {
            $containerId = $job->getDockerContainerId();
            try {
                $container = $this->docker->getClient()->inspectContainer($containerId);
            } catch (\Exception $exception) {
                if ($exception->getCode() === 404) {
                    $text = sprintf('<comment>[warning] No running container found for job #%s</comment>', $job->getId());

                    if ($dryRun === false) {
                        $text = sprintf('<comment>[warning] No running container found for job #%s, changing state to "stopped"</comment>', $job->getId());

                        $job->setState(BaseJob::STATE_STOPPED);
                        $em->persist($job);
                        $em->flush();

                    } else {
                        $text = sprintf('[warning] No running container found for job #%s, but no action is performed as <comment>--dry-run</comment>', $job->getId());
                    }

                    $output->writeln($text);
                }
            }
        }

        return 0;
    }
}
