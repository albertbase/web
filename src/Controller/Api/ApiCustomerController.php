<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\RealTimeNotificationService;

#[Route('/customer', name: 'api_customer_')]
#[IsGranted('ROLE_USER')]
class ApiCustomerController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/categories', name: 'categories', methods: ['GET'])]
    public function categories(CategoryRepository $categoryRepository): JsonResponse
    {
        $categories = [];

        foreach ($categoryRepository->findAllSorted() as $category) {
            $categories[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'productCount' => $category->getProducts()->count(),
            ];
        }

        return $this->apiSuccess('Categories retrieved successfully', [
            'categories' => $categories,
        ]);
    }

    #[Route('/products', name: 'products', methods: ['GET'])]
    public function products(Request $request, ProductRepository $productRepository): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $category = trim((string) $request->query->get('category', ''));
        $limit = (int) $request->query->get('limit', 50);
        $limit = max(1, min(100, $limit));
        $includeOutOfStock = filter_var(
            $request->query->get('includeOutOfStock', false),
            FILTER_VALIDATE_BOOL
        );

        $products = $productRepository->findByMenuFilters(
            $search !== '' ? $search : null,
            $category !== '' ? $category : null
        );

        if (!$includeOutOfStock) {
            $products = array_values(array_filter(
                $products,
                static fn (Product $product): bool => ($product->getStock() ?? 0) > 0
            ));
        }

        $products = array_slice($products, 0, $limit);

        return $this->apiSuccess('Products retrieved successfully', [
            'products' => array_map(
                fn (Product $product): array => $this->mapProduct($product, $request),
                $products
            ),
            'filters' => [
                'search' => $search,
                'category' => $category,
                'limit' => $limit,
                'includeOutOfStock' => $includeOutOfStock,
            ],
        ]);
    }

    #[Route('/products/{id<\d+>}', name: 'product_show', methods: ['GET'])]
    public function product(int $id, Request $request, ProductRepository $productRepository): JsonResponse
    {
        $product = $productRepository->find($id);

        if (!$product instanceof Product) {
            return $this->apiError('Product not found.', 404);
        }

        return $this->apiSuccess('Product retrieved successfully', [
            'product' => $this->mapProduct($product, $request),
        ]);
    }

    #[Route('/orders', name: 'orders', methods: ['GET'])]
    public function orders(OrderRepository $orderRepository): JsonResponse
    {
        $user = $this->getApiUser();

        $orders = $orderRepository->createQueryBuilder('o')
            ->leftJoin('o.orderItems', 'oi')
            ->addSelect('oi')
            ->leftJoin('oi.product', 'p')
            ->addSelect('p')
            ->andWhere('o.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->apiSuccess('Orders retrieved successfully', [
            'orders' => array_map(
                fn (Order $order): array => $this->mapOrder($order),
                $orders
            ),
        ]);
    }

    #[Route('/orders/{id<\d+>}', name: 'orders_show', methods: ['GET'])]
    public function order(int $id, OrderRepository $orderRepository): JsonResponse
    {
        $user = $this->getApiUser();
        $order = $orderRepository->find($id);

        if (!$order instanceof Order || $order->getCreatedBy()?->getId() !== $user->getId()) {
            return $this->apiError('Order not found.', 404);
        }

        return $this->apiSuccess('Order retrieved successfully', [
            'order' => $this->mapOrder($order),
        ]);
    }

    #[Route('/orders', name: 'orders_create', methods: ['POST'])]
    public function createOrder(
        Request $request,
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository,
        RealTimeNotificationService $realTimeNotificationService,
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        $itemsPayload = $data['items'] ?? null;
        if (!is_array($itemsPayload) || $itemsPayload === []) {
            return $this->apiError('At least one order item is required.', 400);
        }

        $lineItems = [];
        foreach ($itemsPayload as $item) {
            if (!is_array($item)) {
                return $this->apiError('Invalid order item payload.', 400);
            }

            $productId = (int) ($item['productId'] ?? $item['id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                return $this->apiError('Each item requires a valid productId and quantity.', 400);
            }

            $lineItems[$productId] = ($lineItems[$productId] ?? 0) + $quantity;
        }

        $requestedProductIds = array_map('intval', array_keys($lineItems));
        $products = $productRepository->findBy(['id' => $requestedProductIds]);
        $productsById = [];

        foreach ($products as $product) {
            $productsById[$product->getId()] = $product;
        }

        $missingProductIds = array_values(array_diff($requestedProductIds, array_keys($productsById)));
        if ($missingProductIds !== []) {
            return $this->apiError('Some products do not exist.', 404, [
                'missingProductIds' => $missingProductIds,
            ]);
        }

        foreach ($lineItems as $productId => $quantity) {
            $product = $productsById[(int) $productId];
            if ($quantity > ($product->getStock() ?? 0)) {
                return $this->apiError(
                    sprintf('Insufficient stock for "%s".', $product->getName()),
                    409,
                    [
                        'productId' => $product->getId(),
                        'requestedQuantity' => $quantity,
                        'availableStock' => $product->getStock(),
                    ]
                );
            }
        }

        $user = $this->getApiUser();
        $customerName = trim((string) ($data['customerName'] ?? ''));
        $customerPhone = trim((string) ($data['customerPhone'] ?? ''));

        $order = new Order();
        $order->setCustomerName($customerName !== '' ? $customerName : ($user->getName() ?: $user->getUserIdentifier()));
        $order->setCustomerPhone($customerPhone !== '' ? substr($customerPhone, 0, 20) : null);
        $order->setStatus(Order::STATUS_PENDING);
        $order->setCreatedBy($user);

        $total = 0.0;
        $entityManager->beginTransaction();

        try {
            foreach ($productsById as $product) {
                $entityManager->lock($product, LockMode::PESSIMISTIC_WRITE);
            }

            foreach ($lineItems as $productId => $quantity) {
                $product = $productsById[(int) $productId];

                if ($quantity > (int) $product->getStock()) {
                    $entityManager->rollback();
                    return $this->apiError(
                        sprintf('Insufficient stock for "%s".', $product->getName()),
                        409,
                        [
                            'productId' => $product->getId(),
                            'requestedQuantity' => $quantity,
                            'availableStock' => $product->getStock(),
                        ]
                    );
                }

                $subtotal = round((float) $product->getPrice() * $quantity, 2);

                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($quantity);
                $orderItem->setSubtotal($subtotal);
                $order->addOrderItem($orderItem);

                $product->setStock((int) $product->getStock() - $quantity);
                $total += $subtotal;
            }

            $order->setTotalAmount(round($total, 2));

            $entityManager->persist($order);
            $entityManager->flush();
            $entityManager->commit();

            $realTimeNotificationService->publishOrderCreated($order);
            foreach ($productsById as $product) {
                $realTimeNotificationService->publishInventoryUpdate($product);
            }
            $realTimeNotificationService->publishNotification(
                'New order placed',
                sprintf(
                    'Order #%d from %s — ₱%s.',
                    $order->getId(),
                    $order->getCustomerName(),
                    number_format((float) $order->getTotalAmount(), 2),
                ),
                'success',
            );
        } catch (\Throwable $exception) {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->rollback();
            }

            return $this->apiError('Unable to place order right now. Please try again.', 500);
        }

        return $this->apiSuccess('Order placed successfully.', [
            'order' => $this->mapOrder($order),
        ], 201);
    }

    #[Route('/orders/{id<\d+>}/cancel', name: 'orders_cancel', methods: ['PATCH'])]
    public function cancelOrder(
        int $id,
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager,
        RealTimeNotificationService $realTimeNotificationService,
    ): JsonResponse {
        $user = $this->getApiUser();
        $order = $orderRepository->find($id);

        if (!$order instanceof Order || $order->getCreatedBy()?->getId() !== $user->getId()) {
            return $this->apiError('Order not found.', 404);
        }

        if ($order->getStatus() !== Order::STATUS_PENDING) {
            return $this->apiError(
                sprintf('Cannot cancel an order with status "%s". Only pending orders can be cancelled.', $order->getStatus()),
                409
            );
        }

        $entityManager->beginTransaction();
        try {
            foreach ($order->getOrderItems() as $item) {
                $product = $item->getProduct();
                if ($product instanceof Product) {
                    $entityManager->lock($product, LockMode::PESSIMISTIC_WRITE);
                    $product->setStock((int) $product->getStock() + (int) $item->getQuantity());
                }
            }

            $order->setStatus(Order::STATUS_CANCELLED);
            $entityManager->flush();
            $entityManager->commit();

            $realTimeNotificationService->publishOrderStatusUpdate($order);
            foreach ($order->getOrderItems() as $item) {
                $product = $item->getProduct();
                if ($product instanceof Product) {
                    $realTimeNotificationService->publishInventoryUpdate($product);
                }
            }
        } catch (\Throwable $exception) {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->rollback();
            }

            return $this->apiError('Unable to cancel order right now. Please try again.', 500);
        }

        return $this->apiSuccess('Order cancelled successfully.', [
            'order' => $this->mapOrder($order),
        ]);
    }

    #[Route('/orders/{id<\d+>}/pay', name: 'orders_pay', methods: ['POST'])]
    public function payOrder(
        int $id,
        Request $request,
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getApiUser();
        $order = $orderRepository->find($id);

        if (!$order instanceof Order || $order->getCreatedBy()?->getId() !== $user->getId()) {
            return $this->apiError('Order not found.', 404);
        }

        if ($order->getStatus() !== Order::STATUS_PENDING) {
            return $this->apiError(
                sprintf('Cannot pay for an order with status "%s". Only pending orders can be paid.', $order->getStatus()),
                409
            );
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        $paymentMethod = trim((string) ($data['paymentMethod'] ?? ''));
        $allowedMethods = ['cash', 'gcash', 'card', 'bank_transfer'];

        if ($paymentMethod === '' || !in_array($paymentMethod, $allowedMethods, true)) {
            return $this->apiError(
                sprintf('Invalid payment method. Allowed: %s.', implode(', ', $allowedMethods)),
                400
            );
        }

        $order->setStatus(Order::STATUS_PAID);
        $entityManager->flush();

        return $this->apiSuccess('Payment processed successfully.', [
            'payment' => [
                'orderId' => $order->getId(),
                'amount' => $order->getTotalAmount(),
                'method' => $paymentMethod,
                'status' => 'completed',
                'paidAt' => (new \DateTime())->format(DATE_ATOM),
            ],
            'order' => $this->mapOrder($order),
        ]);
    }

    private function mapProduct(Product $product, Request $request): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'image' => $product->getImage(),
            'imageUrl' => $this->buildImageUrl($request, $product->getImage()),
            'category' => $product->getCategory()?->getName(),
            'createdAt' => $product->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    private function mapOrder(Order $order): array
    {
        $items = [];
        $itemsCount = 0;

        foreach ($order->getOrderItems() as $item) {
            $product = $item->getProduct();
            $itemName = $product ? $product->getName() : 'Custom Cake';
            $customization = $item->getCakeCustomization();

            $items[] = [
                'id' => $item->getId(),
                'productId' => $product?->getId(),
                'name' => $itemName,
                'quantity' => $item->getQuantity(),
                'unitPrice' => $product?->getPrice(),
                'subtotal' => $item->getSubtotal(),
                'customization' => $customization ? [
                    'size' => $customization->getSize(),
                    'flavor' => $customization->getFlavor(),
                    'decorations' => $customization->getDecorations(),
                    'message' => $customization->getMessage(),
                    'price' => $customization->getPrice(),
                ] : null,
            ];
            $itemsCount += (int) $item->getQuantity();
        }

        return [
            'id' => $order->getId(),
            'customerName' => $order->getCustomerName(),
            'customerPhone' => $order->getCustomerPhone(),
            'status' => $order->getStatus(),
            'totalAmount' => $order->getTotalAmount(),
            'itemsCount' => $itemsCount,
            'items' => $items,
            'createdAt' => $order->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    private function buildImageUrl(Request $request, ?string $image): ?string
    {
        if ($image === null || $image === '') {
            return null;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').'/uploads/products/'.$image;
    }

    private function getApiUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authenticated user is required.');
        }

        return $user;
    }
}
