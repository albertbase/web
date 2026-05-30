<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LoginSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLogger $activityLogger,
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

        // 🛑 Skip logging for stateless API firewall since it fires on *every* request.
        // Actual API logins are logged manually in ApiLoginController and ApiGoogleLoginController.
        if ($event->getFirewallName() === 'api') {
            return;
        }

        $this->activityLogger->log(
            $user,
            'LOGIN',
            'User',
            $user->getId(),
            ['username' => $user->getUserIdentifier()]
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();

        $request = $event->getRequest();
        if ($request->hasSession()) {
            $request->getSession()->remove('cart');
        }

        // ✅ Ensure it's your User entity
        if (!$user instanceof User) {
            return;
        }

        $user->markSessionEnded();
        $this->entityManager->flush();

        $this->activityLogger->log(
            $user,
            'LOGOUT',
            'User',
            $user->getId(),
            ['username' => $user->getUserIdentifier()]
        );
    }
}
