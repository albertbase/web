<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private RealTimeNotificationService $realTimeNotificationService,
    ) {}

    /**
     * Log an activity and optionally attach product data (for order actions).
     *
     * @param array<array{name: string, quantity: int, subtotal: float}> $products
     */
    public function log(
        ?UserInterface $user,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        $details = null,   // allow array or string
        array $products = [],
    ): void {
        $log = new ActivityLog();
        $log->setAction(strtoupper($action));
        $log->setTimestamp(new \DateTime());

        $username = null;
        $userRole = null;

        if ($user) {
            $username = $user->getUserIdentifier();
            $userRole = implode(', ', $user->getRoles());
            $log->setUsername($username);
            $log->setUserRole($userRole);
        }

        $log->setEntityType($entityType);
        $log->setEntityId($entityId);

        // ⭐ Build a human-readable summary that includes product names
        $summary = null;
        if ($products !== []) {
            $productNames = array_map(
                static fn (array $p): string => sprintf('%s ×%d', $p['name'], (int) ($p['quantity'] ?? 1)),
                $products,
            );
            $summary = implode(', ', $productNames);
        }

        // ⭐ Convert arrays to readable text
        if (is_array($details)) {
            $details = $this->formatDetails($details);
        }

        // Append product summary to details when present
        $fullDetails = trim(implode(' | ', array_filter([
            $details,
            $summary !== null ? 'Products: ' . $summary : null,
        ])));

        $log->setDetails($fullDetails !== '' ? $fullDetails : $details);

        $this->em->persist($log);
        $this->em->flush();

        // ⭐ Broadcast to admin panel via Mercure
        $this->realTimeNotificationService->publishActivityLog([
            'id'         => $log->getId(),
            'username'   => $username,
            'userRole'   => $userRole,
            'action'     => $log->getAction(),
            'entityType' => $entityType,
            'entityId'   => $entityId,
            'details'    => $log->getDetails(),
            'summary'    => $summary,
            'products'   => $products,
            'timestamp'  => $log->getTimestamp()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Build a product list from an Order entity for use as the $products argument.
     *
     * @return array<array{name: string, quantity: int, subtotal: float}>
     */
    public static function productsFromOrder(Order $order): array
    {
        $products = [];
        foreach ($order->getOrderItems() as $item) {
            $products[] = [
                'name'     => $item->getProduct()?->getName() ?? 'Custom Cake',
                'quantity' => (int) $item->getQuantity(),
                'subtotal' => (float) $item->getSubtotal(),
            ];
        }
        return $products;
    }

    private function formatDetails(array $data): string
    {
        return implode(', ', array_map(
            fn($key, $value) => "$key: $value",
            array_keys($data),
            $data
        ));
    }
}
