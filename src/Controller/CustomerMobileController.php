<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ApiTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CustomerMobileController extends AbstractController
{
    #[Route('/customer/mobile', name: 'customer_mobile')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(ApiTokenService $apiTokenService): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        return $this->render('customer/mobile.html.twig', [
            'apiToken' => $apiTokenService->generate($user),
        ]);
    }
}
