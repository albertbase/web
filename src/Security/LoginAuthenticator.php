<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\LogService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $userRepository,
        private LogService $logService
    ) {}

    public function authenticate(Request $request): Passport
    {
        $username = '';
        $password = '';
        $csrfToken = '';

        if (method_exists($request, 'getPayload')) {
            $payload = $request->getPayload();
            $username = trim((string) $payload->getString('username'));
            $password = (string) $payload->getString('password');
            $csrfToken = (string) $payload->getString('_csrf_token');
        }

        if ($username === '') {
            $username = trim((string) $request->request->get('username', ''));
        }
        if ($password === '') {
            $password = (string) $request->request->get('password', '');
        }
        if ($csrfToken === '') {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        return new Passport(
            new UserBadge($username, function (string $userIdentifier): User {
                $user = $this->userRepository->findOneByNormalizedUsername($userIdentifier);

                if (!$user instanceof User) {
                    throw new CustomUserMessageAuthenticationException('Invalid credentials.');
                }

                if ($this->isVerificationRequired($user) && !$user->isVerified()) {
                    throw new CustomUserMessageAuthenticationException('Please verify your email');
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        /** @var User $user */
        $user = $token->getUser();

        $this->logService->logAndFlush(
            action: 'LOGIN',
            entityType: 'User',
            entityId: $user->getId(),
            changes: ['username' => $user->getUserIdentifier()]
        );

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
        }

        if (in_array('ROLE_STAFF', $user->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('admin_products'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_landing'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    private function isVerificationRequired(User $user): bool
    {
        return !in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
