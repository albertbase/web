<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ApiDataController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/users', name: 'api_users', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function users(UserRepository $userRepository): JsonResponse
    {
        $users = array_map(
            fn (User $user): array => [
                'id' => $user->getId(),
                'username' => $user->getUserIdentifier(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
                'isVerified' => $user->isVerified(),
                'isActive' => $user->isActive(),
                'authProvider' => method_exists($user, 'getAuthProvider') ? $user->getAuthProvider() : 'local',
                'lastLogin' => $user->getLastLogin()?->format(DATE_ATOM),
                'createdAt' => $user->getCreatedAt()?->format(DATE_ATOM),
            ],
            $userRepository->findBy([], ['id' => 'DESC'])
        );

        return $this->apiSuccess('Data retrieved successfully', [
            'users' => $users,
        ]);
    }

    #[Route('/products', name: 'api_products', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function products(ProductRepository $productRepository): JsonResponse
    {
        $products = array_map(
            fn (Product $product): array => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'stock' => $product->getStock(),
                'image' => $product->getImage(),
                'category' => $product->getCategory()?->getName(),
                'createdAt' => $product->getCreatedAt()?->format(DATE_ATOM),
            ],
            $productRepository->findBy([], ['id' => 'DESC'])
        );

        return $this->apiSuccess('Data retrieved successfully', [
            'products' => $products,
        ]);
    }

    #[Route('/data', name: 'api_data', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function data(EntityManagerInterface $entityManager): JsonResponse
    {
        $userCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->getQuery()
            ->getSingleScalarResult();

        $verifiedUserCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.isVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();

        $staffCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_STAFF%')
            ->getQuery()
            ->getSingleScalarResult();

        $adminCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();

        $productCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Product::class, 'p')
            ->getQuery()
            ->getSingleScalarResult();

        $lowStockCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Product::class, 'p')
            ->andWhere('p.stock <= :threshold')
            ->setParameter('threshold', 5)
            ->getQuery()
            ->getSingleScalarResult();

        $inventoryValue = (float) $entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(p.price * p.stock), 0)')
            ->from(Product::class, 'p')
            ->getQuery()
            ->getSingleScalarResult();

        $orderCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(Order::class, 'o')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->apiSuccess('Data retrieved successfully', [
            'users' => [
                'total' => $userCount,
                'verified' => $verifiedUserCount,
                'staff' => $staffCount,
                'admin' => $adminCount,
            ],
            'products' => [
                'total' => $productCount,
                'lowStock' => $lowStockCount,
                'inventoryValue' => round($inventoryValue, 2),
            ],
            'orders' => [
                'total' => $orderCount,
            ],
        ]);
    }
}
