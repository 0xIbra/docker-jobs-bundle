<?php

namespace IterativeCode\DockerJobsBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use IterativeCode\DockerJobsBundle\Entity\BaseJob;
use IterativeCode\DockerJobsBundle\Entity\Repository\BaseJobRepository;
use IterativeCode\DockerJobsBundle\Event\JobCanceledEvent;
use IterativeCode\DockerJobsBundle\Event\JobStoppedEvent;
use IterativeCode\DockerJobsBundle\Event\JobSubmittedEvent;
use IterativeCode\DockerJobsBundle\Form\SubmitType;
use IterativeCode\DockerJobsBundle\Service\DockerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class MonitoringController
{
    use HttpControllerTrait;

    /** @var DockerService */
    private $docker;

    /** @var string */
    private $jobClass;

    /** @var string */
    private $defaultDockerImage;

    /** @var BaseJobRepository */
    private $jobRepository;

    public function __construct(DockerService $docker, string $jobClass, string $defaultDockerImage)
    {
        $this->docker = $docker;
        $this->jobClass = $jobClass;
        $this->defaultDockerImage = $defaultDockerImage;
    }

    private function init()
    {
        $this->jobRepository = $this->em->getRepository($this->jobClass);
    }

    /**
     * @Route(
     *     "/jobs/dashboard",
     *     name="iterative_code.docker_jobs.index",
     *     methods={"GET"}
     * )
     */
    public function indexAction(Request $request)
    {
        $this->init();
        $period = 'today';
        if ($request->query->has('period')) {
            $period = $request->query->get('period');
        }
        $queue = 'all';
        if ($request->query->has('queue')) {
            $queue = $request->query->get('queue');
        }

        $stats = $this->jobRepository->getJobStatistics($period);
        $queues = $this->jobRepository->getAggregatedQueues();

        $latestJobsLimit = 5;
        $options = [
            'queue' => $queue,
            'limit' => $latestJobsLimit,
        ];
        $latestRunningJobs = $this->jobRepository->findByState(BaseJob::STATE_RUNNING, $options);
        $latestFinishedJobs = $this->jobRepository->findByState(BaseJob::STATE_FINISHED, $options);
        $latestFailedJobs = $this->jobRepository->findByState(BaseJob::STATE_FAILED, $options);

        return $this->render('@DockerJobs/index.html.twig', [
            'statistics'        => $stats,
            'periods'           => BaseJobRepository::getPeriods(),
            'selectedPeriod'    => $period,
            'selectedQueue'     => $queue,
            'latestJobs'        => [
                'running'   => $latestRunningJobs,
                'finished'  => $latestFinishedJobs,
                'failed'    => $latestFailedJobs,
            ],
            'queues' => $queues
        ]);
    }

    /**
     * @Route(
     *     "/jobs",
     *     name="iterative_code.docker_jobs.jobs.explorer",
     *     methods={"GET"}
     * )
     */
    public function jobsExplorerAction(Request $request)
    {
        $this->init();
        $periods = BaseJobRepository::getPeriods();
        $queues = $this->jobRepository->getAggregatedQueues();
        $states = BaseJob::getStates();

        $selected = [
            'id' => null,
            'command'   => null,
            'period'    => 'today',
            'queue'     => 'all',
            'state'     => null,
            'limit'     => 24,
            'page'      => 1
        ];

        if ($request->query->has('filters')) {
            try {
                $filters = json_decode($request->query->get('filters'), true);

                if (!empty($filters['id'])) {
                    $selected['id'] = $filters['id'];
                }

                if (!empty($filters['command'])) {
                    $selected['command'] = $filters['command'];
                }

                if (!empty($filters['period'])) {
                    $selected['period'] = $filters['period'];
                }

                if (!empty($filters['queue'])) {
                    $selected['queue'] = $filters['queue'];
                }

                if (!empty($filters['state'])) {
                    $selected['state'] = $filters['state'];
                }

                if (!empty($filters['page'])) {
                    $selected['page'] = $filters['page'];
                }

            } catch (\Exception $e) {}
        }

        $pagination = $this->jobRepository->paginate($selected);

        return $this->render('@DockerJobs/explorer.html.twig', [
            'pagination' => $pagination,
            'periods' => $periods,
            'queues' => $queues,
            'states' => $states,
            'selectedValues' => $selected
        ]);
    }

    /**
     * @Route(
     *     "/jobs/{id}/cancel",
     *     name="iterative_code.docker_jobs.jobs.cancel",
     *     methods={"DELETE"},
     *     requirements={"id": "\d+"}
     * )
     */
    public function cancelJob($id)
    {
        $this->init();
        $job = $this->jobRepository->find($id);

        if ($job === null) {
            return new JsonResponse([
                'status' => 404,
                'message' => sprintf('Job with ID %s was not found.', $id),
            ], 404);
        }

        if (in_array($job->getState(), [BaseJob::STATE_FAILED, BaseJob::STATE_FINISHED, BaseJob::STATE_RUNNING])) {
            return new JsonResponse([
                'status' => 400,
                'message' => 'Job is running or has already finished.'
            ], 400);
        }

        if ($job->getState() === BaseJob::STATE_CANCELED) {
            return new JsonResponse([
                'status' => 200,
                'message' => 'Job already canceled'
            ]);
        }

        $job
            ->setState(BaseJob::STATE_CANCELED)
        ;

        $this->em->persist($job);
        $this->em->flush();

        $event = new JobCanceledEvent();
        $event->setJob($job);
        $this->eventDispatcher->dispatch($event, $event->getCode());

        return new JsonResponse([
            'status' => 200,
            'message' => 'Job canceled successfully'
        ]);
    }

    /**
     * @Route(
     *     "/jobs/{id}",
     *     name="iterative_code.docker_jobs.jobs.details",
     *     methods={"GET"},
     *     requirements={"id": "\d+"}
     * )
     */
    public function jobDetailsAction($id)
    {
        $this->init();
        $jobRepository = $this->em->getRepository($this->jobClass);
        $job = $jobRepository->find($id);
        if ($job === null) {
            throw new EntityNotFoundException(sprintf('Job with ID %s was not found.', $id));
        }

        $dockerImageId = $this->defaultDockerImage;
        if ($job->getDockerImageId() !== null) {
            $dockerImageId = $job->getDockerImageId();
        }

        return $this->render('@DockerJobs/Job/details.html.twig', [
            'job' => $job,
            'dockerImageId' => $dockerImageId,
        ]);
    }

    /**
     * @Route(
     *     "/jobs/{id}/details",
     *     name="iterative_code.docker_jobs.jobs.details.json",
     *     methods={"GET"},
     *     requirements={"id": "\d+"}
     * )
     */
    public function jobUpdatedDetails($id, Request $request)
    {
        $this->init();
        /** @var BaseJob $job */
        $job = $this->jobRepository->find($id);
        if ($job === null) {
            throw new EntityNotFoundException(sprintf('Job with ID %s was not found.', $id));
        }
        $data = [
            'state' => $job->getState(),
        ];

        $startedAt = $job->getStartedAt();
        $stoppedAt = $job->getStoppedAt();
        if ($stoppedAt === null) {
            $stoppedAt = new \DateTime();
        }

        if ($startedAt !== null) {
            $data['runtime'] = gmdate('H:i:s', $stoppedAt->getTimestamp() - $startedAt->getTimestamp());
        }

        $containerId = $job->getDockerContainerId();
        if ($containerId !== null) {
            try {
                $data = array_merge($this->docker->getContainerUsage($containerId), $data);
            } catch (\Exception $e) {}
        }

        if ($request->query->has('logs') && $request->query->getBoolean('logs') === true) {
            $data['logs'] = $job->getOutput();
        }

        return new JsonResponse([
            'status' => 200,
            'data' => $data,
        ]);
    }

    /**
     * @Route(
     *     "/jobs/{id}/stop",
     *     name="iterative_code.docker_jobs.jobs.stop",
     *     methods={"POST"},
     *     requirements={"id": "\d+"}
     * )
     */
    public function stopJob($id)
    {
        $this->init();
        $job = $this->jobRepository->find($id);
        if (null === $job) {
            return new JsonResponse([
                'status' => 404,
                'message' => sprintf('Job with ID %s was not found.', $id),
            ], 404);
        }

        if ($job->getState() !== BaseJob::STATE_RUNNING) {
            return new JsonResponse([
                'status' => 200,
                'message' => sprintf('Job is not currently running.'),
            ]);
        }

        try {
            $containerId = $job->getDockerContainerId();
            $container = $this->docker->getClient()->inspectContainer($containerId);

            if ($container['State']['Status'] === 'running') {
                $stopped = $this->docker->stopJob($job);
                if ($stopped === true) {
                    $job->setState(BaseJob::STATE_STOPPED);
                    $job->setStoppedAt(new \DateTime());
                    $this->em->persist($job);
                    $this->em->flush();

                    $event = new JobStoppedEvent();
                    $event->setJob($job);
                    $this->eventDispatcher->dispatch($event, $event->getCode());

                    return new JsonResponse([
                        'status' => 202,
                        'message' => 'Job successfully stopped.',
                    ]);
                }
            } else {
                return new JsonResponse([
                    'status' => 200,
                    'message' => 'Job already stopped or has finished.',
                ]);
            }
        } catch (\Exception $e) {}

        return new JsonResponse([
            'status' => 500,
            'message' => 'Could not stop the Job.',
        ], 500);
    }

    /**
     * @Route(
     *     "/jobs/new/submit",
     *     name="iterative_code.docker_jobs.jobs.submit",
     *     methods={"GET", "POST"}
     * )
     */
    public function submitJobAction(Request $request)
    {
        $this->init();
        $job = new $this->jobClass();
        $job->setDockerImageId($this->defaultDockerImage);

        $form = $this->createForm(SubmitType::class, $job, ['dockerImageId' => $this->defaultDockerImage]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $job = $form->getData();

            $this->em->persist($job);
            $this->em->flush();

            $event = new JobSubmittedEvent();
            $event->setJob($job);
            $this->eventDispatcher->dispatch($event, $event->getCode());

            return $this->redirectToRoute('iterative_code.docker_jobs.jobs.details', ['id' => $job->getId()]);
        }

        return $this->render('@DockerJobs/Job/submit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
