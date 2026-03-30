<?php

namespace App\Service;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;


class LogService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {}

    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        $changes = null   // allow array or string
    ): void {
        $log = new ActivityLog();
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setTimestamp(new \DateTimeImmutable());

        // ⭐ Convert arrays to readable text instead of JSON
        if (is_array($changes)) {
            $changes = $this->formatChanges($changes);
        }

        if ($changes !== null) {
            $log->setDetails($changes);
        }

        $user = $this->security->getUser();
        if ($user) {
            $log->setUsername($user->getUserIdentifier());
            $log->setUserRole(implode(', ', $user->getRoles()));
        }

        $this->em->persist($log);
    }

    public function logAndFlush(
        string $action,
        string $entityType,
        ?int $entityId = null,
        $changes = null
    ): void {
        $this->log($action, $entityType, $entityId, $changes);
        $this->em->flush();
    }

    private function formatChanges(array $changes): string
    {
        return implode(', ', array_map(
            fn($key, $value) => "$key: $value",
            array_keys($changes),
            $changes
        ));
    }
}
