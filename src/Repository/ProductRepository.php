<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return Product[]
     */
    public function findByMenuFilters(?string $searchTerm, ?string $categoryName): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->orderBy('p.createdAt', 'DESC');

        if ($searchTerm) {
            $qb->andWhere('LOWER(p.name) LIKE :search')
                ->setParameter('search', '%'.strtolower(trim($searchTerm)).'%');
        }

        if ($categoryName) {
            $qb->andWhere('LOWER(c.name) = :category')
                ->setParameter('category', strtolower(trim($categoryName)));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Product[]
     */
    public function findByFilters(?string $searchTerm, ?string $categoryName): array
    {
        return $this->findByMenuFilters($searchTerm, $categoryName);
    }

    public function findWithCreator(int $id): ?Product
    {
        return $this->createQueryBuilder('p')
            ->addSelect('createdBy', 'category')
            ->leftJoin('p.createdBy', 'createdBy')
            ->leftJoin('p.category', 'category')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Product[]
     */
    public function findForAdminList(?string $search, ?int $categoryId, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('c', 'createdBy')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.createdBy', 'createdBy')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :search OR LOWER(p.description) LIKE :search')
                ->setParameter('search', '%'.strtolower(trim($search)).'%');
        }

        if ($categoryId !== null) {
            $qb->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        return $qb->getQuery()->getResult();
    }
}
