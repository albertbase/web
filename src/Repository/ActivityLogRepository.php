<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Search logs with optional filters.
     * All parameters are nullable.
     */
    public function searchLogs(?string $user, ?array $actions, ?string $dateFrom, ?string $dateTo): array
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.timestamp', 'DESC');

        // ✅ Filter by username
        if ($user) {
            $qb->andWhere('l.username LIKE :user')
               ->setParameter('user', "%$user%");
        }

        // ✅ Filter by actions (array)
        if ($actions && count($actions) > 0) {
            $qb->andWhere('l.action IN (:actions)')
               ->setParameter('actions', $actions);
        }

        // ✅ Filter by date from
        if ($dateFrom) {
            $qb->andWhere('l.timestamp >= :from')
               ->setParameter('from', new \DateTimeImmutable("$dateFrom 00:00:00"));
        }

        // ✅ Filter by date to
        if ($dateTo) {
            $qb->andWhere('l.timestamp <= :to')
               ->setParameter('to', new \DateTimeImmutable("$dateTo 23:59:59"));
        }

        return $qb->getQuery()->getResult();
    }
}
