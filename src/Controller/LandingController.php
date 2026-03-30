<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class LandingController extends AbstractController
{
    #[Route('/landing', name: 'app_landing')]
    public function index(SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);
        $cartCount = array_sum(array_column($cart, 'quantity'));

        return $this->render('landing/index.html.twig', [
            
            'cartCount' => $cartCount,
        ]);
    }
}
