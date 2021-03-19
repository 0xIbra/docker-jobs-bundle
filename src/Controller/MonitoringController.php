<?php

namespace Polkovnik\DockerJobsBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Polkovnik\DockerJobsBundle\Entity\BaseJob;
use Polkovnik\DockerJobsBundle\Entity\Repository\BaseJobRepository;
use Polkovnik\DockerJobsBundle\Event\JobCanceledEvent;
use Polkovnik\DockerJobsBundle\Event\JobStoppedEvent;
use Polkovnik\DockerJobsBundle\Event\JobSubmittedEvent;
use Polkovnik\DockerJobsBundle\Form\SubmitType;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MonitoringController extends Controller
{
    /** @var EntityManagerInterface */
    private $em;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var string */
    private $jobClass;

    /** @var BaseJobRepository */
    private $jobRepository;

    private function init()
    {
        $this->em = $this->container->get('doctrine')->getManager();
        $this->eventDispatcher = $this->container->get('event_dispatcher');
        $this->jobClass = $this->container->getParameter('docker_jobs.class.job');
        $this->jobRepository = $this->em->getRepository($this->jobClass);
    }

    /**
     * @Route(
     *     "/jobs/dashboard",
     *     name="polkovnik.docker_jobs.index",
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
     *     name="polkovnik.docker_jobs.jobs.explorer",
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
     *     name="polkovnik.docker_jobs.jobs.cancel",
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
        $this->eventDispatcher->dispatch($event->getCode(), $event);

        return new JsonResponse([
            'status' => 200,
            'message' => 'Job canceled successfully'
        ]);
    }

    /**
     * @Route(
     *     "/jobs/{id}",
     *     name="polkovnik.docker_jobs.jobs.details",
     *     methods={"GET"},
     *     requirements={"id": "\d+"}
     * )
     */
    public function jobDetailsAction($id)
    {
        $this->init();

        $em = $this->container->get('doctrine')->getManager();
        $jobRepository = $em->getRepository($this->container->getParameter('docker_jobs.class.job'));
        $job = $jobRepository->find($id);
        if ($job === null) {
            throw new EntityNotFoundException(sprintf('Job with ID %s was not found.', $id));
        }

        $dockerImageId = $this->container->getParameter('docker_jobs.docker.default_image_id');
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
     *     name="polkovnik.docker_jobs.jobs.details.json",
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

        $docker = $this->container->get('polkovnik.docker_jobs.service.docker');
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
                $data = array_merge($docker->getContainerUsage($containerId), $data);
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
     *     name="polkovnik.docker_jobs.jobs.stop",
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

        $docker = $this->container->get('polkovnik.docker_jobs.service.docker');
        try {
            $containerId = $job->getDockerContainerId();
            $container = $docker->getClient()->inspectContainer($containerId);

            if ($container['State']['Status'] === 'running') {
                $stopped = $docker->stopJob($job);
                if ($stopped === true) {
                    $job->setState(BaseJob::STATE_STOPPED);
                    $job->setStoppedAt(new \DateTime());
                    $this->em->persist($job);
                    $this->em->flush();

                    $event = new JobStoppedEvent();
                    $event->setJob($job);
                    $this->eventDispatcher->dispatch($event->getCode(), $event);

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
     *     name="polkovnik.docker_jobs.jobs.submit",
     *     methods={"GET", "POST"}
     * )
     */
    public function submitJobAction(Request $request)
    {
        $this->init();

        $defaultDockerImage = $this->container->getParameter('docker_jobs.docker.default_image_id');
        $job = new $this->jobClass();
        $job->setDockerImageId($defaultDockerImage);

        $form = $this->createForm(SubmitType::class, $job, ['dockerImageId' => $defaultDockerImage]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $job = $form->getData();

            $this->em->persist($job);
            $this->em->flush();

            $event = new JobSubmittedEvent();
            $event->setJob($job);
            $this->eventDispatcher->dispatch($event->getCode(), $event);

            return $this->redirectToRoute('polkovnik.docker_jobs.jobs.details', ['id' => $job->getId()]);
        }

        return $this->render('@DockerJobs/Job/submit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
