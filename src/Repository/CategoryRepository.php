<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    //    /**
    //     * @return Category[] Returns an array of Category objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Category
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, array{category: Category, productCount: int}>
     */
    public function findAllWithProductCounts(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c AS category, COUNT(p.id) AS productCount')
            ->leftJoin('c.products', 'p')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static function (array $row): array {
            return [
                'category' => $row['category'],
                'productCount' => (int) $row['productCount'],
            ];
        }, $rows);
    }

    public function findOneByNormalizedName(string $name): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.name) = :name')
            ->setParameter('name', strtolower(trim($name)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findUsedByProducts(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.products', 'p')
            ->groupBy('c.id')
            ->getQuery()
            ->getResult();
    }


}
