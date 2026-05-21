<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private RouterInterface $router
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);
        /** @var GoogleUser $googleUser */
        $googleUser = $client->fetchUserFromToken($accessToken);
        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();
        $name = $googleUser->getName();

        return new SelfValidatingPassport(
            new UserBadge($email, function () use ($email, $name, $googleId) {
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $email]);

                if (!$user) {
                    $user = new User();
                    $user->setUsername($email);
                    $user->setName($name ?: $email);
                    $user->setRoles(['ROLE_STAFF']);
                    $user->setGoogleId($googleId);
                    $user->setAuthProvider('google');
                    $user->setStatus('active');
                    $user->setIsActive(true);
                    $user->setIsVerified(true);
                    $user->setCreatedAt(new \DateTimeImmutable());
                    $user->setPassword(
                        $this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32)))
                    );
                    $user->setLastLogin(new \DateTimeImmutable());
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                    return $user;
                }

                if (in_array('ROLE_ADMIN', $user->getAssignedRoles(), true)) {
                    throw new CustomUserMessageAuthenticationException(
                        'Admin accounts cannot sign in with Google.'
                    );
                }

                if ($user->isCustomerOnly()) {
                    throw new CustomUserMessageAuthenticationException(
                        'Google sign-in is only available for staff accounts. Please use your email and password.'
                    );
                }

                if (!$user->isVerified()) {
                    $user->setIsVerified(true);
                }

                if (!$user->getGoogleId() && $googleId) {
                    $user->setGoogleId($googleId);
                }

                if ($user->getAuthProvider() !== 'google') {
                    $user->setAuthProvider('google');
                }

                if (!$user->getName() && $name) {
                    $user->setName($name);
                }

                $user->setLastLogin(new \DateTimeImmutable());
                $this->entityManager->flush();

                return $user;
            }),
            [
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if ($request->hasSession()) {
            $request->getSession()->set('authenticated_user_id', $user instanceof User ? $user->getId() : null);
            $request->getSession()->set('authenticated_user_identifier', $user instanceof User ? $user->getUserIdentifier() : null);

            $displayName = $user instanceof User && $user->getName()
                ? $user->getName()
                : 'there';

            $request->getSession()->getFlashBag()->add(
                'success',
                sprintf('Welcome back, %s. Google sign-in was successful.', $displayName)
            );
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new RedirectResponse($this->router->generate('admin_dashboard'));
        }

        return new RedirectResponse($this->router->generate('admin_products'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add(
                'danger',
                $exception instanceof CustomUserMessageAuthenticationException
                    ? $exception->getMessage()
                    : 'Google sign in failed. Please try again.'
            );
        }

        return new RedirectResponse($this->router->generate('app_login'));
    }
}
