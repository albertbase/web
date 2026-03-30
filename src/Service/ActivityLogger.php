<?php

namespace App\Service;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActivityLogger
{
    public function __construct(private EntityManagerInterface $em) {}

    public function log(
        ?UserInterface $user,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        $details = null   // allow array or string
    ): void {
        $log = new ActivityLog();
        $log->setAction($action);
        $log->setTimestamp(new \DateTime());

        if ($user) {
            $log->setUsername($user->getUserIdentifier());
            $log->setUserRole(implode(', ', $user->getRoles()));
        }

        $log->setEntityType($entityType);
        $log->setEntityId($entityId);

        // ⭐ Convert arrays to readable text
        if (is_array($details)) {
            $details = $this->formatDetails($details);
        }

        $log->setDetails($details);

        $this->em->persist($log);
        $this->em->flush();
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
