<?php

namespace App\Controller;


use App\Entity\User;
use App\Entity\Product;
use App\Form\ProductType;
use App\Form\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
 use App\Entity\Order;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Repository\ActivityLogRepository;
use App\Form\UserType;
use App\Service\ActivityLogger;
use App\Service\LogService;
use Symfony\Component\Security\Http\Attribute\IsGranted as AttributeIsGranted;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(EntityManagerInterface $em, ActivityLogRepository $logRepo): Response
    {
        $totalProducts = $em->getRepository(Product::class)->count([]);
        $totalUsers = $em->getRepository(\App\Entity\User::class)->count([]);

        $totalStaff = $em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(\App\Entity\User::class, 'u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_STAFF%')
            ->getQuery()
            ->getSingleScalarResult();

        $totalStock = $em->createQueryBuilder()
            ->select('SUM(p.stock)')
            ->from(Product::class, 'p')
            ->getQuery()
            ->getSingleScalarResult();

        $totalValue = $em->createQueryBuilder()
            ->select('SUM(p.price * p.stock)')
            ->from(Product::class, 'p')
            ->getQuery()
            ->getSingleScalarResult();

        $totalOrders = $em->getRepository(\App\Entity\Order::class)->count([]);
        $totalCategories = $em->getRepository(\App\Entity\Category::class)->count([]);

        // Recent orders
        $recentOrders = $em->createQueryBuilder()
            ->select('o')
            ->from(\App\Entity\Order::class, 'o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Recent products
        $recentProducts = $em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // ✅ Recent activity logs
        $recentLogs = $logRepo->findBy([], ['timestamp' => 'DESC'], 10);

        return $this->render('admin/dashboard.html.twig', [
            'totalProducts' => $totalProducts,
            'totalUsers' => $totalUsers,
            'totalStaff' => $totalStaff,
            'totalStock' => $totalStock,
            'totalValue' => $totalValue,
            'totalOrders' => $totalOrders,
            'totalCategories' => $totalCategories,
            'recentOrders' => $recentOrders,
            'recentProducts' => $recentProducts,
            'recentLogs' => $recentLogs,
        ]);
    }

    /**
     * Session-authenticated JSON feed for dashboard live polling (main firewall).
     */
    #[Route('/admin/dashboard/live', name: 'admin_dashboard_live', methods: ['GET'])]
    public function dashboardLive(EntityManagerInterface $em): JsonResponse
    {
        $totalProducts = $em->getRepository(Product::class)->count([]);
        $totalUsers = $em->getRepository(User::class)->count([]);

        $totalStaff = (int) $em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_STAFF%')
            ->getQuery()
            ->getSingleScalarResult();

        $totalOrders = $em->getRepository(Order::class)->count([]);

        $recentOrders = $em->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        $recentProducts = $em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'success' => true,
            'metrics' => [
                'totalProducts' => $totalProducts,
                'totalUsers' => $totalUsers,
                'totalStaff' => $totalStaff,
                'totalOrders' => $totalOrders,
            ],
            'recentOrders' => array_map(static function (Order $order): array {
                return [
                    'id' => $order->getId(),
                    'customerName' => $order->getCustomerName(),
                    'status' => $order->getStatus(),
                    'totalAmount' => $order->getTotalAmount(),
                    'createdAt' => $order->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                ];
            }, $recentOrders),
            'recentProducts' => array_map(static function (Product $product): array {
                return [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'price' => $product->getPrice(),
                    'stock' => $product->getStock(),
                    'categoryName' => $product->getCategory()?->getName(),
                    'image' => $product->getImage(),
                ];
            }, $recentProducts),
        ]);
    }



    // #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/users', name: 'admin_users')]
    public function users(UserRepository $userRepo): Response
    {
        $users = $userRepo->findAll();

        return $this->render('users/users.html.twig', [
            'users' => $users,
        ]);
    }
    // #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/users/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function newUser(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set default password if not provided
            if (!$user->getPassword()) {
                $hashedPassword = $passwordHasher->hashPassword($user, 'password123');
                $user->setPassword($hashedPassword);
            }

            $user->setCreatedAt(new \DateTime());

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'User created successfully.');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('users/user_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    // #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/users/edit/{id}', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function editUser(User $user, Request $request, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $activityLogger->log($this->getUser(), 'UPDATE', 'User', $user->getId(), 'Updated user: ' . $user->getUsername());

            $this->addFlash('success', 'User updated successfully.');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('users/user_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    // #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/users/delete/{id}', name: 'admin_users_delete', methods: ['POST'])]
    public function deleteUser(User $user, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();

            $this->addFlash('success', 'User deleted successfully.');
        }

        return $this->redirectToRoute('admin_users');
    }

    // #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/users/login-as/{id}', name: 'admin_users_login_as')]
    public function loginAs(User $user): Response
    {
        // simplest placeholder: flash message
        $this->addFlash('info', 'Pretending to log in as ' . $user->getUsername());

        // later you can implement Symfony impersonation via security.yaml
        return $this->redirectToRoute('admin_users');
    }

    // #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/logs', name: 'admin_logs')]
    public function logs(ActivityLogRepository $logRepo, Request $request): Response
    {
        $action = $request->query->get('action');
        $username = $request->query->get('username');

        $qb = $logRepo->createQueryBuilder('l')
            ->orderBy('l.timestamp', 'DESC');

        if ($action) {
            $qb->andWhere('l.action = :action')
               ->setParameter('action', $action);
        }

        if ($username) {
            $qb->andWhere('l.username = :username')
               ->setParameter('username', $username);
        }

        $logs = $qb->getQuery()->getResult();

        return $this->render('admin/logs.html.twig', [
            'logs' => $logs,
            'action' => $action,
            'username' => $username,
        ]);
    }

//     return $this->render('admin/order_edit.html.twig', [
//         'order' => $order,
//     ]);
//     }

// }

#[Route('/admin/system', name: 'admin_system')]
public function SystemDashboard(ActivityLogRepository $activityLogRepository): Response
    {
        // Fetch logs from DB, newest first
        $logs = $activityLogRepository->findBy([], ['timestamp' => 'DESC']);

        return $this->render('admin/data_access_logs/data_access.html.twig', [
            'logs' => $logs,
        ]);
    }

}
