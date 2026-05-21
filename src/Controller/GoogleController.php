<?php

// src/Controller/GoogleController.php
namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectAction(ClientRegistry $clientRegistry): Response
    {
        // Redirect to Google with the scopes needed for staff login.
        return $clientRegistry
            ->getClient('google')
            ->redirect([
                'email',
                'profile',
            ], [
                'prompt' => 'select_account',
            ]);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheckAction(): Response
    {
        // This route is processed by App\Security\GoogleAuthenticator.
        return new Response('', Response::HTTP_OK);
    }
}
