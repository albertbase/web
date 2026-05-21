<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ApiMeController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        return $this->apiSuccess('Data retrieved successfully', [
            'user' => [
                'id' => $user?->getId(),
                'username' => $user?->getUserIdentifier(),
                'name' => method_exists($user, 'getName') ? $user->getName() : null,
                'roles' => method_exists($user, 'getRoles') ? $user->getRoles() : [],
                'isVerified' => method_exists($user, 'isVerified') ? $user->isVerified() : false,
            ],
        ]);
    }
}
