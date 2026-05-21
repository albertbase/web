<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Security\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ApiProfileController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/profile', name: 'api_profile_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function profile(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->apiError('Authenticated user not found.', 401);
        }

        return $this->apiSuccess('Profile retrieved successfully', [
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUserIdentifier(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
                'isVerified' => $user->isVerified(),
            ],
        ]);
    }

    #[Route('/profile', name: 'api_profile_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->apiError('Authenticated user not found.', 401);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable $exception) {
            return $this->apiError('Invalid JSON payload.', 400);
        }

        $name = isset($data['name']) ? trim((string) $data['name']) : null;
        $password = isset($data['password']) ? (string) $data['password'] : null;

        if ($name !== null) {
            $user->setName($name);
        }

        if ($password !== null && $password !== '') {
            $currentPassword = (string) ($data['currentPassword'] ?? '');
            if ($currentPassword === '' || !$passwordHasher->isPasswordValid($user, $currentPassword)) {
                return $this->apiError('Current password is incorrect.', 403);
            }

            if (!PasswordPolicy::isValid($password)) {
                return $this->apiError(
                    PasswordPolicy::MESSAGE,
                    400
                );
            }

            $user->setPassword($passwordHasher->hashPassword($user, $password));
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->apiSuccess('Profile updated successfully', [
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
