<?php

namespace Polkovnik\DockerJobsBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Polkovnik\DockerJobsBundle\Entity\BaseJob;
use Polkovnik\DockerJobsBundle\Entity\Repository\BaseJobRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MonitoringController extends AbstractController
{
    /**
     * @Route(
     *     "/jobs/dashboard",
     *     name="polkovnik.docker_jobs.index",
     *     methods={"GET"}
     * )
     */
    public function indexAction(Request $request)
    {
        $period = 'today';
        if ($request->query->has('period')) {
            $period = $request->query->get('period');
        }
        $queue = 'all';
        if ($request->query->has('queue')) {
            $queue = $request->query->get('queue');
        }

        $em = $this->get('doctrine')->getManager();
        /** @var BaseJobRepository $jobRepository */
        $jobRepository = $em->getRepository($this->container->getParameter('docker_jobs.class.job'));

        $stats = $jobRepository->getJobStatistics($period);
        $queues = $jobRepository->getAggregatedQueues();

        $latestJobsLimit = 5;
        $options = [
            'queue' => $queue,
            'limit' => $latestJobsLimit,
        ];
        $latestRunningJobs = $jobRepository->findByState(BaseJob::STATE_RUNNING, $options);
        $latestFinishedJobs = $jobRepository->findByState(BaseJob::STATE_FINISHED, $options);
        $latestFailedJobs = $jobRepository->findByState(BaseJob::STATE_FAILED, $options);

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
        /** @var EntityManagerInterface $em */
        $em = $this->get('doctrine')->getManager();

        /** @var BaseJobRepository $jobRepository */
        $jobRepository = $em->getRepository($this->container->getParameter('docker_jobs.class.job'));

        $periods = BaseJobRepository::getPeriods();
        $queues = $jobRepository->getAggregatedQueues();
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

        $pagination = $jobRepository->paginate($selected);

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
     *     "/jobs/{id}/delete",
     *     name="polkovnik.docker_jobs.jobs.cancel",
     *     methods={"DELETE"},
     *     requirements={"id": "\d+"}
     * )
     */
    public function deleteJob($id)
    {
        $em = $this->get('doctrine')->getManager();

        /** @var BaseJobRepository $jobRepository */
        $jobRepository = $em->getRepository($this->container->getParameter('docker_jobs.class.job'));
        $job = $jobRepository->find($id);

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

        $em->persist($job);
        $em->flush();

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
        $em = $this->container->get('doctrine')->getManager();
        $jobRepository = $em->getRepository($this->container->getParameter('docker_jobs.class.job'));
        $job = $jobRepository->find($id);
        if ($job === null) {
            throw new EntityNotFoundException(sprintf('Job with ID %s was not found.', $id));
        }

        return $this->render('@DockerJobs/Job/details.html.twig', [
            'job' => $job,
        ]);
    }
}
