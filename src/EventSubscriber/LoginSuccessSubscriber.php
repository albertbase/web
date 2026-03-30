<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\LogService;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LoginSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(private LogService $logService) {}

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

        // ✅ Ensure it's your User entity
        if (!$user instanceof User) {
            return;
        }

        $this->logService->logAndFlush(
            'LOGOUT',
            'User',
            $user->getId(),
            ['username' => $user->getUserIdentifier()]
        );
    }
}
