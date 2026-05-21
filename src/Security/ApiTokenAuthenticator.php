<?php

namespace App\Security;

use App\Entity\User;
use App\Service\ApiTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private ApiTokenService $apiTokenService,
        private EntityManagerInterface $entityManager
    ) {}

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api/')
            && $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $authorization = $request->headers->get('Authorization', '');
        $token = trim(substr($authorization, 7));

        if ($token === '') {
            throw new CustomUserMessageAuthenticationException('Missing bearer token.');
        }

        try {
            $payload = $this->apiTokenService->verify($token);
        } catch (\Throwable $e) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired token.');
        }

        return new SelfValidatingPassport(
            new UserBadge((string) ($payload['sub'] ?? ''), function (string $userIdentifier) use ($payload): User {
                $user = $this->entityManager->getRepository(User::class)->find((int) $userIdentifier);

                if (!$user instanceof User) {
                    throw new CustomUserMessageAuthenticationException('Token user not found.');
                }

                if ($user->getUserIdentifier() !== (string) ($payload['username'] ?? '')) {
                    throw new CustomUserMessageAuthenticationException('Token subject mismatch.');
                }

                if ($this->requiresVerification($user) && !$user->isVerified()) {
                    throw new CustomUserMessageAuthenticationException('Email not verified');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => $exception instanceof CustomUserMessageAuthenticationException
                ? $exception->getMessage()
                : 'Unauthorized',
        ], 401);
    }

    private function requiresVerification(User $user): bool
    {
        return !in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
