<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\Order;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class RealTimeNotificationService
{
    public function __construct(
        private HubInterface $hub
    ) {}

    /**
     * Publishes real-time stock and product information updates.
     */
    public function publishInventoryUpdate(Product $product): void
    {
        $update = new Update(
            'https://sweetoria.app/inventory',
            json_encode([
                'productId' => $product->getId(),
                'name' => $product->getName(),
                'stock' => $product->getStock(),
                'price' => $product->getPrice(),
                'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM)
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publishes order status transitions.
     */
    public function publishOrderStatusUpdate(Order $order): void
    {
        $items = [];
        foreach ($order->getOrderItems() as $item) {
            $items[] = [
                'name' => $item->getProduct()?->getName() ?? 'Custom Cake',
                'quantity' => $item->getQuantity(),
            ];
        }

        $update = new Update(
            sprintf('https://sweetoria.app/orders/%d', $order->getId()),
            json_encode([
                'orderId' => $order->getId(),
                'customerName' => $order->getCustomerName(),
                'status' => $order->getStatus(),
                'totalAmount' => $order->getTotalAmount(),
                'items' => $items,
                'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM)
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publishes global system alerts/notifications (e.g. low stock, new registrations, system events).
     */
    public function publishNotification(string $title, string $message, string $type = 'info'): void
    {
        $update = new Update(
            'https://sweetoria.app/notifications',
            json_encode([
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM)
            ])
        );

        $this->hub->publish($update);
    }
}
