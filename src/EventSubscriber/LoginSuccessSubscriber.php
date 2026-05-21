<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\LogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LoginSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LogService $logService,
        private EntityManagerInterface $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class       => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        // ✅ Ensure it's your User entity
        if (!$user instanceof User) {
            return;
        }

        $user->markSessionStarted();
        $user->setLastLogin(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->logService->logAndFlush(
            'LOGIN',
            'User',
            $user->getId(),
            ['username' => $user->getUserIdentifier()]
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();

        $event->getRequest()->getSession()->remove('cart');

        // ✅ Ensure it's your User entity
        if (!$user instanceof User) {
            return;
        }

        $user->markSessionEnded();
        $this->entityManager->flush();

        $this->logService->logAndFlush(
            'LOGOUT',
            'User',
            $user->getId(),
            ['username' => $user->getUserIdentifier()]
        );
    }
}
