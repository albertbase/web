<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\ApiTokenService;
use App\Service\LogService;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ApiLoginController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ApiTokenService $apiTokenService,
        LogService $logService
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        $username = trim((string) ($data['username'] ?? $data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->apiError(
                'username (or email) and password are required in the JSON body. Use Content-Type: application/json.',
                400
            );
        }

        $user = $userRepository->findOneByNormalizedUsername($username);

        if (!$user instanceof User || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->apiError('Invalid credentials', 401);
        }

        if (!$user->isVerified()) {
            return $this->apiError('Email not verified', 403);
        }

        $user->markSessionStarted();
        $user->setLastLogin(new \DateTimeImmutable());
        $entityManager->flush();

        $logService->logAndFlush(
            'LOGIN',
            'User',
            $user->getId(),
            ['username' => $user->getUserIdentifier(), 'source' => 'API']
        );

        $token = $apiTokenService->generate($user);

        return $this->apiSuccess('Login successful', [
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => 86400,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUserIdentifier(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
                'isVerified' => $user->isVerified(),
            ],
        ]);
    }
}
