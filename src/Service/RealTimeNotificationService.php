<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Product;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publishes Mercure events for mobile app + admin (topics must match client subscriptions).
 */
class RealTimeNotificationService
{
    /** Topics the Sweetoria mobile app subscribes to (MercureContext.tsx). */
    private const MOBILE_TOPICS = [
        'inventory',
        'order_status',
        'notification',
        'new_order',
        'order_created',
        'orders',
        'admin_orders',
    ];

    public function __construct(
        private readonly HubInterface $hub,
    ) {}

    public function publishInventoryUpdate(Product $product): void
    {
        $this->publish(
            [
                'productId' => $product->getId(),
                'id' => $product->getId(),
                'name' => $product->getName(),
                'stock' => $product->getStock(),
                'price' => $product->getPrice(),
                'category' => $product->getCategory()?->getName(),
                'imageUrl' => $product->getImage(),
                'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ],
            'inventory',
        );
    }

    public function publishOrderCreated(Order $order): void
    {
        $payload = $this->buildOrderPayload($order);
        $payload['event'] = 'order_created';
        $payload['type'] = 'new_order';
        $payload['action'] = 'created';
        $payload['title'] = 'New order';
        $payload['message'] = sprintf('New order #%d from %s.', $order->getId(), $order->getCustomerName());

        $this->publish($payload, 'order_created');
        $this->publish($payload, 'new_order');
        $this->publish($payload, 'notification');
    }

    public function publishOrderStatusUpdate(Order $order): void
    {
        $payload = $this->buildOrderPayload($order);
        $payload['event'] = 'order_status';
        $payload['type'] = 'order_status';
        $payload['title'] = 'Order updated';
        $payload['message'] = sprintf(
            'Order #%d status: %s.',
            $order->getId(),
            (string) $order->getStatus(),
        );

        $this->publish($payload, 'order_status');
        $this->publish($payload, 'notification');
    }

    public function publishNotification(string $title, string $message, string $type = 'info'): void
    {
        $this->publish(
            [
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'event' => 'notification',
                'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ],
            'notification',
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function publish(array $data, string $eventType): void
    {
        try {
            $update = new Update(
                self::MOBILE_TOPICS,
                json_encode($data, JSON_THROW_ON_ERROR),
                false,
                $eventType,
                'application/json',
            );

            $this->hub->publish($update);
        } catch (\Throwable $exception) {
            // Do not break checkout if Mercure hub is down (e.g. Railway without Mercure service).
            error_log('[Mercure] Publish failed: '.$exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderPayload(Order $order): array
    {
        $items = [];
        foreach ($order->getOrderItems() as $item) {
            $items[] = [
                'name' => $item->getProduct()?->getName() ?? 'Custom Cake',
                'quantity' => $item->getQuantity(),
                'subtotal' => $item->getSubtotal(),
            ];
        }

        return [
            'orderId' => $order->getId(),
            'id' => $order->getId(),
            'customerName' => $order->getCustomerName(),
            'customerPhone' => $order->getCustomerPhone(),
            'status' => $order->getStatus(),
            'totalAmount' => $order->getTotalAmount(),
            'itemsCount' => array_sum(array_map(static fn (array $i): int => (int) ($i['quantity'] ?? 0), $items)),
            'items' => $items,
            'order' => [
                'id' => $order->getId(),
                'customerName' => $order->getCustomerName(),
                'customerPhone' => $order->getCustomerPhone(),
                'status' => $order->getStatus(),
                'totalAmount' => $order->getTotalAmount(),
                'items' => $items,
                'createdAt' => $order->getCreatedAt()?->format(DATE_ATOM),
            ],
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'createdAt' => $order->getCreatedAt()?->format(DATE_ATOM),
        ];
    }
}
