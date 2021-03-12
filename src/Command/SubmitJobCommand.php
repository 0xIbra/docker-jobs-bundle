<?php

namespace Polkovnik\DockerJobsBundle\Command;

use Polkovnik\DockerJobsBundle\Manager\JobManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SubmitJobCommand extends Command
{
    /** @var ContainerInterface */
    private $container;

    /** @var JobManager */
    private $jobManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->jobManager = $container->get('polkovnik.docker_jobs.manager.job');

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('polkovnik:docker-jobs:submit')
            ->setDescription('Creates a new job and submits it to a queue for processing.')
            ->addOption('--command', null, InputOption::VALUE_REQUIRED, 'command to execute.')
            ->addOption('--queue', null, InputOption::VALUE_OPTIONAL, 'queue where to submit the job.', 'default')
            ->addUsage('"run:my:command --arg1=yes"')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $options = $input->getOptions();
        if (empty($options['command'])) {
            throw new \InvalidArgumentException('"--command" argument is required.');
        }

        $queue = $options['queue'];
        $command = $options['command'];

        $job = $this->jobManager->createJob([
            'queue' => $queue,
            'command' => $command,
        ]);

        $output->writeln('<info>Job successfully created.</info>');
    }
}
