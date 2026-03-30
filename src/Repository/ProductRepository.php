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

    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

  public function findByMenuFilters(?string $searchTerm, ?string $categoryName): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c');

        if ($searchTerm) {
            $qb->andWhere('LOWER(p.name) LIKE :search')
               ->setParameter('search', '%' . strtolower($searchTerm) . '%');
        }

        if ($categoryName) {
            $qb->andWhere('c.name = :category')
               ->setParameter('category', $categoryName);
        }

        return $qb->getQuery()->getResult();
    }


    // src/Repository/ProductRepository.php

public function findByFilters(?string $searchTerm, ?string $categoryName): array
{
    $qb = $this->createQueryBuilder('p')
        ->leftJoin('p.category', 'c');

    if ($searchTerm) {
        $qb->andWhere('LOWER(p.name) LIKE :search')
           ->setParameter('search', '%' . strtolower($searchTerm) . '%');
    }

    if ($categoryName) {
        $qb->andWhere('c.name = :category')
           ->setParameter('category', $categoryName);
    }

    return $qb->getQuery()->getResult();
}

public function findWithCreator(int $id): ?Product
{
    return $this->createQueryBuilder('p')
        ->addSelect('createdBy')
        ->leftJoin('p.createdBy', 'createdBy')
        ->andWhere('p.id = :id')
        ->setParameter('id', $id)
        ->getQuery()
        ->getOneOrNullResult();
}





}
