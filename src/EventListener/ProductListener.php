<?php

namespace App\EventListener;

use App\Entity\Product;
use App\Entity\ActivityLog;
use App\Service\LogService;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;

class ProductListener
{
    public function __construct(private LogService $logService) {}

    public function prePersist(Product $product, PrePersistEventArgs $args): void
    {
        // ✅ Prevent recursion: do NOT log ActivityLog inserts
        if ($product instanceof ActivityLog) {
            return;
        }

        $this->logService->logAndFlush(
            'CREATE',
            'Product',
            null,
            ['name' => $product->getName()]
        );
    }

    public function preUpdate(Product $product, PreUpdateEventArgs $args): void
    {
        // ✅ Prevent recursion
        // if ($product instanceof ActivityLog) {
        //     return;
        // }

        // // ✅ Get detected changes BEFORE recompute
        // $changes = $args->getEntityChangeSet();

        // // ✅ REQUIRED for Doctrine ORM 3.x
        // $em = $args->getObjectManager();
        // $meta = $em->getClassMetadata(Product::class);
        // $em->getUnitOfWork()->recomputeSingleEntityChangeSet($meta, $product);

        // // ✅ Log the update
        // $this->logService->logAndFlush(
        //     'UPDATE',
        //     'Product',
        //     $product->getId(),
        //     $changes
        // );
        return;
    }

    public function preRemove(Product $product, PreRemoveEventArgs $args): void
    {
        // ✅ Prevent recursion
        // if ($product instanceof ActivityLog) {
        //     return;
        // }

        // $this->logService->logAndFlush(
        //     'DELETE',
        //     'Product',
        //     $product->getId()
        // );
        return;
    }
}
