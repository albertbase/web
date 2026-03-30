<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    //    /**
    //     * @return Order[] Returns an array of Order objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Order
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function findByFilters(?string $searchTerm, ?string $status): array
{
    $qb = $this->createQueryBuilder('o');

    if ($searchTerm) {
        $qb->andWhere('LOWER(o.customerName) LIKE :search OR LOWER(o.customerEmail) LIKE :search')
           ->setParameter('search', '%' . strtolower($searchTerm) . '%');
    }

    if ($status) {
        $qb->andWhere('o.status = :status')
           ->setParameter('status', $status);
    }

    return $qb->orderBy('o.createdAt', 'DESC')
              ->getQuery()
              ->getResult();
}

public function findWithCreator(int $id): ?Order
{
    return $this->createQueryBuilder('o')
        ->addSelect('createdBy')
        ->leftJoin('o.createdBy', 'createdBy')
        ->andWhere('o.id = :id')
        ->setParameter('id', $id)
        ->getQuery()
        ->getOneOrNullResult();
}


}
