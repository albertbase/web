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

    /**
     * @return Order[]
     */
    public function findByFilters(?string $searchTerm, ?string $status): array
    {
        $qb = $this->createQueryBuilder('o');

        if ($searchTerm) {
            $qb->andWhere('LOWER(o.customerName) LIKE :search OR LOWER(o.customerPhone) LIKE :search')
                ->setParameter('search', '%'.strtolower(trim($searchTerm)).'%');
        }

        if ($status) {
            $qb->andWhere('o.status = :status')
                ->setParameter('status', trim($status));
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

    public function findWithItems(int $id): ?Order
    {
        return $this->createQueryBuilder('o')
            ->addSelect('createdBy', 'items', 'product', 'customization')
            ->leftJoin('o.createdBy', 'createdBy')
            ->leftJoin('o.orderItems', 'items')
            ->leftJoin('items.product', 'product')
            ->leftJoin('items.cakeCustomization', 'customization')
            ->andWhere('o.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Order[]
     */
    public function findForAdminList(?string $searchTerm, ?string $status, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('o')
            ->addSelect('createdBy')
            ->leftJoin('o.createdBy', 'createdBy')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($searchTerm !== null && trim($searchTerm) !== '') {
            $qb->andWhere(
                'LOWER(o.customerName) LIKE :search OR LOWER(COALESCE(o.customerPhone, \'\')) LIKE :search'
            )->setParameter('search', '%'.strtolower(trim($searchTerm)).'%');
        }

        if ($status !== null && trim($status) !== '') {
            $qb->andWhere('o.status = :status')
                ->setParameter('status', trim($status));
        }

        return $qb->getQuery()->getResult();
    }
}
