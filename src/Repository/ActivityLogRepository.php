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
     *
     * @param string[]|null $actions
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
            // Normalise to uppercase so 'create' and 'CREATE' both match
            $actions = array_map('strtoupper', $actions);
            $qb->andWhere('l.action IN (:actions)')
               ->setParameter('actions', $actions);
        }

        // ✅ Filter by date from (also used as "after" cursor for live polling)
        if ($dateFrom) {
            // Accept both 'YYYY-MM-DD' and full ISO-8601 timestamps
            try {
                $fromDt = new \DateTimeImmutable($dateFrom);
            } catch (\Throwable) {
                $fromDt = new \DateTimeImmutable("$dateFrom 00:00:00");
            }
            $qb->andWhere('l.timestamp >= :from')
               ->setParameter('from', $fromDt);
        }

        // ✅ Filter by date to
        if ($dateTo) {
            try {
                $toDt = new \DateTimeImmutable($dateTo);
                // If it looks like a plain date (no time component), go to end of day
                if (!str_contains($dateTo, 'T') && !str_contains($dateTo, ':')) {
                    $toDt = new \DateTimeImmutable("$dateTo 23:59:59");
                }
            } catch (\Throwable) {
                $toDt = new \DateTimeImmutable("$dateTo 23:59:59");
            }
            $qb->andWhere('l.timestamp <= :to')
               ->setParameter('to', $toDt);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Return the most recent $limit logs newer than $afterTimestamp.
     * Used by the live-polling endpoint.
     */
    public function findNewerThan(\DateTimeInterface $afterTimestamp, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.timestamp > :after')
            ->setParameter('after', $afterTimestamp)
            ->orderBy('l.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
