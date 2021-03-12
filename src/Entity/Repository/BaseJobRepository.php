<?php

namespace Polkovnik\DockerJobsBundle\Entity\Repository;

use App\Entity\Job;
use Doctrine\ORM\EntityRepository;
use Polkovnik\DockerJobsBundle\Entity\BaseJob;

class BaseJobRepository extends EntityRepository
{
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

}
