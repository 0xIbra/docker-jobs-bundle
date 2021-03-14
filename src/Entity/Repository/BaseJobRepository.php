<?php

namespace Polkovnik\DockerJobsBundle\Entity\Repository;

use App\Entity\Job;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Polkovnik\DockerJobsBundle\Entity\BaseJob;

class BaseJobRepository extends EntityRepository
{
    public static function getPeriods()
    {
        return [
            'last-hour'     => 'polkovnik.docker_jobs.last_hour',
            'today'         => 'polkovnik.docker_jobs.today',
            'last-7-days'   => 'polkovnik.docker_jobs.last_7_days',
            'currentMonth'  => 'polkovnik.docker_jobs.current_month',
            'lastMonth'     => 'polkovnik.docker_jobs.last_month'
        ];
    }

    /**
     * @return array
     */
    public function findRunnableJobs($queue, $limit = null)
    {
        $qb = $this->createQueryBuilder('j');
        $qb
            ->where('j.state = :state')
            ->setParameter('state', BaseJob::STATE_NEW)
            ->andWhere('j.startedAt IS NULL')
            ->orderBy('j.id', 'DESC')
        ;

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->execute();
    }

    public function findUncheckedJobs()
    {
        $qb = $this->createQueryBuilder('j');
        $qb
            ->select('j.id, j.state, j.createdAt')
            ->where('j.checked IS FALSE')
        ;

        return $qb->getQuery()->execute();
    }

    public function getAggregatedQueues()
    {
        $qb = $this->createQueryBuilder('j');
        $qb
            ->select('j.queue')
            ->groupBy('j.queue')
        ;

        $result = [];
        $queried = $qb->getQuery()->execute();
        foreach ($queried as $queue) {
            $result[] = $queue['queue'];
        }

        return $result;
    }

    public function paginate($options)
    {
        $qb = $this->createQueryBuilder('j');
        $qb = $this->applyFilters($qb, $options);

        $qb2 = clone $qb;
        $total = (int) $qb2->select('count(j.id)')->getQuery()->getSingleScalarResult();

        $page = $options['page'];
        $perPage = $options['limit'];
        $totalPages = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $qb->setMaxResults($perPage);

        if ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        $from = $offset + 1;
        $to = ($offset + $perPage);

        return [
            'jobs' => $qb->getQuery()->execute(),
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'total' => $total,
            'currentPage' => $page,
            'from' => $from,
            'to' => $to,
        ];
    }

    public function findByState($state, $options = [])
    {
        $qb = $this->createQueryBuilder('j');

        if (!empty($options['state'])) {
            $qb
                ->where('j.state = :state')
                ->setParameter('state', $options['state'])
            ;
        }

        if (!empty($options['queue'])) {
            $queue = $options['queue'];
            if ($queue !== null && $queue !== 'all') {
                $qb
                    ->andWhere('j.queue = :queue')
                    ->setParameter('queue', $queue)
                ;
            }
        }

        if (!empty($options['period'])) {
            try {
                $qb = $this->applyPeriod($qb, $options['period']);
            } catch (\Exception $e) {}
        }

        if (!empty($options['limit'])) {
            $qb->setMaxResults($options['limit']);
        }

        $qb->orderBy('j.id', 'DESC');

        return $qb->getQuery()->execute();
    }

    public function getJobStatistics($period = 'today')
    {
        $new = $this->countByState(BaseJob::STATE_NEW, $period);
        $pending = $this->countByState(BaseJob::STATE_PENDING, $period);
        $running = $this->countByState(BaseJob::STATE_RUNNING, $period);
        $finished = $this->countByState(BaseJob::STATE_FINISHED, $period);
        $failed = $this->countByState(BaseJob::STATE_FAILED, $period);

        $periodStats = $this->getJobStatisticsPerPeriod($period);

        $count = [
            'pending' => $new + $pending,
            'running' => $running,
            'finished' => $finished,
            'failed' => $failed,
        ];

        return [
            'count' => $count,
            'countArray' => array_values($count),
            'period' => $periodStats,
        ];
    }

    public function getJobStatisticsPerPeriod($period)
    {
        $qb = $this->createQueryBuilder('j');
        $qb
            ->select('j.createdAt, count(j.id) AS count')
            ->orderBy('j.createdAt', 'ASC')
            ->groupBy('j.createdAt')
        ;

        $qb = $this->applyPeriod($qb, $period);

        $labels = [];
        $values = [];

        $hourMinutes = [10, 20, 30, 40, 50];
        if ($period === 'last-hour') {
            $now = new \DateTime();
            $then = new \DateTime();
            $then->modify('-1 hour');

            while ($then->getTimestamp() <= $now->getTimestamp()) {
                $minute = (int) $then->format('i');
                $minute = $this->closestNumberInArray($minute, $hourMinutes);

                if ($minute !== null) {
                    $labels[] = sprintf('%s:%s', $then->format('H'), $minute);
                }
                $then->modify('+10 minutes');
            }

        } else if ($period === 'today') {
            $then = new \DateTime();
            $then->modify('-24 hours');
            $now = new \DateTime();

            while ($then->getTimestamp() <= $now->getTimestamp()) {
                $key = $then->format('H');
                $labels[] = sprintf('%s:00', $key);
                $values[$key] = 0;

                $then->modify('+1 hour');
            }
        } else if ($period === 'last-7-days') {
            $then = new \DateTime();
            $then->modify('-7 days');
            $now = new \DateTime();

            while ($then->getTimestamp() <= $now->getTimestamp()) {
                $key = $then->format('d/m');
                $labels[] = $key;
                $values[$key] = 0;

                $then->modify('+1 day');
            }
        } else if ($period === 'currentMonth') {
            $then = new \DateTime('first day of this month');
            $now = new \DateTime();

            while ($then->getTimestamp() <= $now->getTimestamp()) {
                $key = $then->format('d/m');
                $labels[] = $key;
                $values[$key] = 0;

                $then->modify('+1 day');
            }
        } else if ($period === 'lastMonth') {
            $then = new \DateTime('first day of last month');
            $thenEnd = new \DateTime('last day of last month');

            while ($then->getTimestamp() <= $thenEnd->getTimestamp()) {
                $key = $then->format('d/m');
                $labels[] = $key;
                $values[$key] = 0;

                $then->modify('+1 day');
            }
        }

        $res = $qb->getQuery()->execute();
        foreach ($res as $item) {
            $date = $item['createdAt'];
            $count = (int) $item['count'];

            if ($period === 'last-hour') {
                $minute = $date->format('i');
                $minute = (string) $this->closestNumberInArray((int) $minute, $hourMinutes);
                if (empty($values[$minute])) {
                    $values[$minute] = $count;
                } else {
                    $values[$minute] += $count;
                }

            } else if ($period === 'today') {
                $hour = $date->format('H');
                if (empty($values[$hour])) {
                    $values[$hour] = $count;
                } else {
                    $values[$hour] += $count;
                }
            } else {
                $dayMonth = $date->format('d/m');
                if (empty($values[$dayMonth])) {
                    $values[$dayMonth] = $count;
                } else {
                    $values[$dayMonth] += $count;
                }
            }
        }

        $values = array_values($values);

        return ['labels' => $labels, 'values' => $values];
    }

    public function countByState($state, $period)
    {
        $qb = $this->createQueryBuilder('j');
        $qb
            ->select('count(j.id)')
            ->where('j.state = :state')
            ->setParameter('state', $state)
        ;

        $qb = $this->applyPeriod($qb, $period);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applyPeriod(QueryBuilder $qb, $period)
    {
        $date = new \DateTime();

        if ($period === 'last-hour') {
            $date->modify('-1 hour');
            $qb
                ->andWhere('j.createdAt > :date')
                ->setParameter('date', $date)
            ;
        } else if ($period === 'today') {
            $date->modify('-24 hours');
            $qb
                ->andWhere('j.createdAt > :date')
                ->setParameter('date', $date)
            ;
        } else if ($period === 'last-7-days') {
            $date->modify('-7 days');
            $qb
                ->andWhere('j.createdAt > :date')
                ->setParameter('date', $date)
            ;
        } else if ($period === 'currentMonth') {
            $date = new \DateTime('first day of this month');
            $qb
                ->andWhere('j.createdAt > :date')
                ->setParameter('date', $date)
            ;
        } else if ($period === 'lastMonth') {
            $date = new \DateTime('first day of last month');
            $date2 = new \DateTime('last day of last month');
            $qb
                ->andWhere('j.createdAt BETWEEN :from AND :to')
                ->setParameter('from', $date)
                ->setParameter('to', $date2)
            ;
        } else {
            throw new \InvalidArgumentException(sprintf('Unsupported period type "%s"', $period));
        }

        return $qb;
    }

    private function applyFilters(QueryBuilder $qb, $filters = [])
    {
        if (!empty($filters['id'])) {
            $qb
                ->andWhere('j.id = :id')
                ->setParameter('id', $filters['id'])
            ;
        }

        if (!empty($filters['command'])) {
            $qb
                ->andWhere('j.command = :command')
                ->setParameter('command', '%' . $filters['command'] . '%')
            ;
        }

        if (!empty($filters['period'])) {
            $qb = $this->applyPeriod($qb, $filters['period']);
        }

        if (!empty($filters['queue']) && $filters['queue'] !== 'all') {
            $qb
                ->andWhere('j.queue = :queue')
                ->setParameter('queue', $filters['queue'])
            ;
        }

        if (!empty($filters['state']) && $filters['state'] !== 'all') {
            $qb
                ->andWhere('j.state = :state')
                ->setParameter('state', $filters['state'])
            ;
        }

        return $qb;
    }

    private function closestNumberInArray($number, $array = [])
    {
        $closest = null;
        foreach ($array as $arrayNumber) {
            if ($closest === null || abs($number - $closest) > abs($arrayNumber - $number)) {
                $closest = $arrayNumber;
            }
        }

        return $closest;
    }
}
