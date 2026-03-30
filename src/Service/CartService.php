<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    private $session;

    public function __construct(RequestStack $requestStack)
    {
        $this->session = $requestStack->getSession();
    }

    public function add(int $productId): void
    {
        $cart = $this->session->get('cart', []);
        $cart[$productId] = ($cart[$productId] ?? 0) + 1;
        $this->session->set('cart', $cart);
    }

    public function getCart(): array
    {
        return $this->session->get('cart', []);
    }

    public function remove(int $productId): void
    {
        $cart = $this->session->get('cart', []);
        unset($cart[$productId]);
        $this->session->set('cart', $cart);
    }

    public function clear(): void
    {
        $this->session->remove('cart');
    }
}
