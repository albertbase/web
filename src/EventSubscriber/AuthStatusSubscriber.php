<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuthStatusSubscriber implements EventSubscriberInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogin(LoginSuccessEvent $event): void
    {
        $currentUser = $event->getUser();

        // ✅ Ensure the logged-in user is actually a User entity
        if (!$currentUser instanceof User) {
            return;
        }

        // ✅ Mark all other users inactive
        $qb = $this->em->createQueryBuilder();
        $qb->update(User::class, 'u')
            ->set('u.status', ':inactive')
            ->set('u.isActive', ':false')
            ->where('u.id != :id')
            ->setParameter('inactive', 'inactive')
            ->setParameter('false', false)
            ->setParameter('id', $currentUser->getId())
            ->getQuery()
            ->execute();

        // ✅ Mark current user active
        $currentUser->setStatus('active');
        $currentUser->setIsActive(true);

        $this->em->flush();
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();

        // ✅ Ensure logout user is a User entity
        if (!$user instanceof User) {
            return;
        }

        // ✅ Mark user inactive
        $user->setStatus('inactive');
        $user->setIsActive(false);

        $this->em->flush();
    }
}
