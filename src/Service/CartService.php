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

    public function addCustomItem(array $customItem): void
    {
        $cart = $this->session->get('cart', []);
        $key = 'custom_'.uniqid();
        $cart[$key] = [
            'type' => 'custom',
            'customization' => $customItem,
            'quantity' => $customItem['quantity'] ?? 1,
        ];

        $this->session->set('cart', $cart);
    }

    public function getCart(): array
    {
        return $this->session->get('cart', []);
    }

    public function updateQuantity(string|int $key, int $quantity): void
    {
        $cart = $this->session->get('cart', []);
        if (!isset($cart[$key])) {
            return;
        }

        if ($quantity <= 0) {
            unset($cart[$key]);
        } else {
            if (is_array($cart[$key]) && ($cart[$key]['type'] ?? null) === 'custom') {
                $cart[$key]['quantity'] = $quantity;
            } else {
                $cart[$key] = $quantity;
            }
        }

        $this->session->set('cart', $cart);
    }

    public function remove(string|int $key): void
    {
        $cart = $this->session->get('cart', []);
        unset($cart[$key]);
        $this->session->set('cart', $cart);
    }

    public function clear(): void
    {
        $this->session->remove('cart');
    }
}
