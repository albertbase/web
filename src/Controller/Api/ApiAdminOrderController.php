<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\RealTimeNotificationService;

#[Route('/admin/orders', name: 'api_admin_orders_')]
#[IsGranted('ROLE_STAFF')]
class ApiAdminOrderController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, OrderRepository $orderRepository): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $status = trim((string) $request->query->get('status', ''));
        $limit = max(1, min(100, (int) $request->query->get('limit', 50)));
        $offset = max(0, (int) $request->query->get('offset', 0));

        if ($status !== '' && !in_array($status, Order::ALLOWED_STATUSES, true)) {
            return $this->apiError('Invalid status filter.', 400);
        }

        $orders = array_map(
            fn (Order $order): array => $this->mapOrder($order),
            $orderRepository->findForAdminList(
                $search !== '' ? $search : null,
                $status !== '' ? $status : null,
                $limit,
                $offset
            )
        );

        return $this->apiSuccess('Orders retrieved successfully.', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id, OrderRepository $orderRepository): JsonResponse
    {
        $order = $orderRepository->findWithItems($id);
        if (!$order instanceof Order) {
            return $this->apiError('Order not found.', 404);
        }

        return $this->apiSuccess('Order retrieved successfully.', [
            'order' => $this->mapOrder($order),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        RealTimeNotificationService $realTimeNotificationService
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        try {
            $lineItems = $this->extractLineItems($data['items'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return $this->apiError($exception->getMessage(), 400);
        }

        $requestedProductIds = array_map('intval', array_keys($lineItems));
        $productsById = $this->fetchProductsById($productRepository, $requestedProductIds);
        $missingProductIds = array_values(array_diff($requestedProductIds, array_keys($productsById)));
        if ($missingProductIds !== []) {
            return $this->apiError('Some products do not exist.', 404, [
                'missingProductIds' => $missingProductIds,
            ]);
        }

        $status = trim((string) ($data['status'] ?? Order::STATUS_PENDING));
        if (!in_array($status, [Order::STATUS_PENDING, Order::STATUS_PAID], true)) {
            return $this->apiError('New orders can only start as Pending or Paid.', 400);
        }

        $order = new Order();
        $order->setCustomerName(trim((string) ($data['customerName'] ?? '')) ?: 'Walk-in Customer');
        $order->setCustomerPhone($this->sanitizePhone($data['customerPhone'] ?? null));
        $order->setStatus($status);

        $createdBy = $this->getUser();
        if ($createdBy instanceof User) {
            $order->setCreatedBy($createdBy);
        }

        $entityManager->beginTransaction();
        try {
            foreach ($productsById as $product) {
                $entityManager->lock($product, LockMode::PESSIMISTIC_WRITE);
            }

            $total = 0.0;
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

                $item = new OrderItem();
                $item->setProduct($product);
                $item->setQuantity($quantity);
                $item->setSubtotal($subtotal);
                $order->addOrderItem($item);

                $product->setStock((int) $product->getStock() - $quantity);
                $total += $subtotal;
            }

            $order->setTotalAmount($total);

            $violations = $validator->validate($order);
            if (count($violations) > 0) {
                $entityManager->rollback();
                return $this->apiError('Validation failed.', 422, [
                    'errors' => $this->validationErrors($violations),
                ]);
            }

            $entityManager->persist($order);
            $entityManager->flush();
            $entityManager->commit();

            // Real-time updates and notifications:
            $realTimeNotificationService->publishOrderCreated($order);
            foreach ($order->getOrderItems() as $item) {
                $product = $item->getProduct();
                if ($product instanceof Product) {
                    $realTimeNotificationService->publishInventoryUpdate($product);
                }
            }
            $realTimeNotificationService->publishNotification(
                'New Order Received',
                sprintf('Order #%d was created by %s for a total of $%s.', $order->getId(), $order->getCustomerName(), $order->getTotalAmount()),
                'success'
            );
        } catch (\Throwable $exception) {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->rollback();
            }

            return $this->apiError('Unable to create order right now. Please try again.', 500);
        }

        return $this->apiSuccess('Order created successfully.', [
            'order' => $this->mapOrder($order),
        ], 201);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        RealTimeNotificationService $realTimeNotificationService
    ): JsonResponse {
        $order = $orderRepository->findWithItems($id);
        if (!$order instanceof Order) {
            return $this->apiError('Order not found.', 404);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        if (array_key_exists('customerName', $data)) {
            $name = trim((string) $data['customerName']);
            if ($name === '') {
                return $this->apiError('customerName cannot be empty.', 400);
            }
            $order->setCustomerName($name);
        }

        if (array_key_exists('customerPhone', $data)) {
            $order->setCustomerPhone($this->sanitizePhone($data['customerPhone']));
        }

        if (array_key_exists('status', $data)) {
            $newStatus = trim((string) $data['status']);
            if (!in_array($newStatus, Order::ALLOWED_STATUSES, true)) {
                return $this->apiError('Invalid order status.', 400);
            }

            if (!$this->canTransitionStatus((string) $order->getStatus(), $newStatus)) {
                return $this->apiError(
                    sprintf('Invalid status transition from "%s" to "%s".', $order->getStatus(), $newStatus),
                    409
                );
            }

            $order->setStatus($newStatus);
        }

        $hasItemsUpdate = array_key_exists('items', $data);
        $lineItems = [];
        $productsById = [];

        if ($hasItemsUpdate) {
            if ($order->getStatus() !== Order::STATUS_PENDING) {
                return $this->apiError('Only pending orders can have line items modified.', 409);
            }

            try {
                $lineItems = $this->extractLineItems($data['items']);
            } catch (\InvalidArgumentException $exception) {
                return $this->apiError($exception->getMessage(), 400);
            }

            $requestedProductIds = array_map('intval', array_keys($lineItems));
            $productsById = $this->fetchProductsById($productRepository, $requestedProductIds);
            $missingProductIds = array_values(array_diff($requestedProductIds, array_keys($productsById)));
            if ($missingProductIds !== []) {
                return $this->apiError('Some products do not exist.', 404, [
                    'missingProductIds' => $missingProductIds,
                ]);
            }
        }

        $entityManager->beginTransaction();
        try {
            if ($hasItemsUpdate) {
                foreach ($order->getOrderItems() as $existingItem) {
                    $existingProduct = $existingItem->getProduct();
                    if ($existingProduct instanceof Product) {
                        $entityManager->lock($existingProduct, LockMode::PESSIMISTIC_WRITE);
                        $existingProduct->setStock(
                            (int) $existingProduct->getStock() + (int) $existingItem->getQuantity()
                        );
                    }
                }

                foreach ($productsById as $product) {
                    $entityManager->lock($product, LockMode::PESSIMISTIC_WRITE);
                }

                foreach ($order->getOrderItems()->toArray() as $existingItem) {
                    $order->removeOrderItem($existingItem);
                }

                $total = 0.0;
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

                    $item = new OrderItem();
                    $item->setProduct($product);
                    $item->setQuantity($quantity);
                    $item->setSubtotal($subtotal);
                    $order->addOrderItem($item);

                    $product->setStock((int) $product->getStock() - $quantity);
                    $total += $subtotal;
                }

                $order->setTotalAmount($total);
            } else {
                $order->setTotalAmount($this->calculateTotalFromCurrentItems($order));
            }

            $violations = $validator->validate($order);
            if (count($violations) > 0) {
                $entityManager->rollback();
                return $this->apiError('Validation failed.', 422, [
                    'errors' => $this->validationErrors($violations),
                ]);
            }

            $entityManager->flush();
            $entityManager->commit();

            // Real-time order update:
            $realTimeNotificationService->publishOrderStatusUpdate($order);
            // Real-time inventory updates for products inside the order:
            foreach ($order->getOrderItems() as $item) {
                $product = $item->getProduct();
                if ($product instanceof Product) {
                    $realTimeNotificationService->publishInventoryUpdate($product);
                }
            }
            // Real-time notifications:
            $realTimeNotificationService->publishNotification(
                'Order Updated',
                sprintf('Order #%d has been updated. Status: %s.', $order->getId(), $order->getStatus()),
                'info'
            );
        } catch (\Throwable $exception) {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->rollback();
            }

            return $this->apiError('Unable to update order right now. Please try again.', 500);
        }

        return $this->apiSuccess('Order updated successfully.', [
            'order' => $this->mapOrder($order),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        int $id,
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $order = $orderRepository->findWithItems($id);
        if (!$order instanceof Order) {
            return $this->apiError('Order not found.', 404);
        }

        if (!in_array($order->getStatus(), [Order::STATUS_PENDING, Order::STATUS_CANCELLED], true)) {
            return $this->apiError('Only pending or cancelled orders can be deleted.', 409);
        }

        $entityManager->beginTransaction();
        try {
            if ($order->getStatus() === Order::STATUS_PENDING) {
                foreach ($order->getOrderItems() as $item) {
                    $product = $item->getProduct();
                    if ($product instanceof Product) {
                        $entityManager->lock($product, LockMode::PESSIMISTIC_WRITE);
                        $product->setStock((int) $product->getStock() + (int) $item->getQuantity());
                    }
                }
            }

            $entityManager->remove($order);
            $entityManager->flush();
            $entityManager->commit();
        } catch (\Throwable $exception) {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->rollback();
            }

            return $this->apiError('Unable to delete order right now. Please try again.', 500);
        }

        return $this->apiSuccess('Order deleted successfully.');
    }

    /**
     * @return array<int, int>
     */
    private function extractLineItems(mixed $itemsPayload): array
    {
        if (!is_array($itemsPayload) || $itemsPayload === []) {
            throw new \InvalidArgumentException('At least one order item is required.');
        }

        $lineItems = [];
        foreach ($itemsPayload as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Invalid order item payload.');
            }

            $productId = (int) ($item['productId'] ?? $item['id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                throw new \InvalidArgumentException('Each item requires a valid productId and quantity.');
            }

            $lineItems[$productId] = ($lineItems[$productId] ?? 0) + $quantity;
        }

        return $lineItems;
    }

    /**
     * @param int[] $productIds
     *
     * @return array<int, Product>
     */
    private function fetchProductsById(ProductRepository $productRepository, array $productIds): array
    {
        $products = $productRepository->findBy(['id' => $productIds]);
        $mapped = [];
        foreach ($products as $product) {
            $mapped[$product->getId()] = $product;
        }

        return $mapped;
    }

    private function sanitizePhone(mixed $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $value = trim((string) $phone);
        return $value === '' ? null : substr($value, 0, 20);
    }

    private function canTransitionStatus(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        $map = [
            Order::STATUS_PENDING => [Order::STATUS_PAID, Order::STATUS_CANCELLED],
            Order::STATUS_PAID => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
            Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED],
            Order::STATUS_DELIVERED => [],
            Order::STATUS_CANCELLED => [],
        ];

        return in_array($to, $map[$from] ?? [], true);
    }

    private function calculateTotalFromCurrentItems(Order $order): float
    {
        $total = 0.0;
        foreach ($order->getOrderItems() as $item) {
            $total += (float) $item->getSubtotal();
        }

        return round($total, 2);
    }

    private function mapOrder(Order $order): array
    {
        $items = [];
        $itemsCount = 0;

        foreach ($order->getOrderItems() as $item) {
            $product = $item->getProduct();
            $customization = $item->getCakeCustomization();

            $items[] = [
                'id' => $item->getId(),
                'productId' => $product?->getId(),
                'name' => $product?->getName() ?? 'Custom Cake',
                'quantity' => $item->getQuantity(),
                'unitPrice' => $product?->getPrice() ?? $customization?->getPrice(),
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
            'createdBy' => $order->getCreatedBy() ? [
                'id' => $order->getCreatedBy()?->getId(),
                'username' => $order->getCreatedBy()?->getUserIdentifier(),
            ] : null,
            'createdAt' => $order->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    private function validationErrors(iterable $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $field = $violation->getPropertyPath() ?: 'general';
            $errors[$field][] = $violation->getMessage();
        }

        return $errors;
    }
}
