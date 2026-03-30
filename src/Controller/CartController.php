<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Repository\ProductRepository;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

// ... (namespace and use statements remain unchanged)

class CartController extends AbstractController
{
    #[Route('/cart', name: 'cart_show')]
    public function show(CartService $cartService, EntityManagerInterface $em): Response
    {
        $cartData = [];
        $total = 0;

        foreach ($cartService->getCart() as $productId => $quantity) {
            $product = $em->getRepository(Product::class)->find($productId);
            if ($product) {
                $subtotal = $product->getPrice() * $quantity;
                $cartData[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ];
                $total += $subtotal;
            }
        }

        return $this->render('cart/show.html.twig', [
            'cart' => $cartData,
            'total' => $total,
        ]);
    }

    #[Route('/cart/add/{id}', name: 'cart_add', methods: ['POST'])]
    public function addToCart(
        int $id,
        Request $request,
        SessionInterface $session,
        ProductRepository $repo,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $token = new CsrfToken('cart_add_' . $id, $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $product = $repo->find($id);
        if (!$product) {
            $this->addFlash('danger', 'Product not found.');
            return $this->redirectToRoute('product_list');
        }

        $cart = $session->get('cart', []);
        $currentQty = $cart[$id] ?? 0;

        if ($currentQty < $product->getStock()) {
            $cart[$id] = $currentQty + 1;
            $session->set('cart', $cart);
            $this->addFlash('success', 'Item added to cart!');
        } else {
            $this->addFlash('warning', 'Cannot add more — stock limit reached.');
        }

        return $this->redirectToRoute('product_list');
    }

    #[Route('/cart/update/{id}/{action}', name: 'cart_update', methods: ['POST'])]
    public function updateCart(
        int $id,
        string $action,
        SessionInterface $session,
        ProductRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $cart = $session->get('cart', []);

        if (!isset($cart[$id])) {
            $this->addFlash('warning', 'Item not found in cart.');
            return $this->redirectToRoute('cart_show');
        }

        $product = $repo->find($id);
        if (!$product) {
            $this->addFlash('danger', 'Product not found.');
            return $this->redirectToRoute('cart_show');
        }

        if ($action === 'increase') {
            if ($cart[$id] < $product->getStock()) {
                $cart[$id]++;
            } else {
                $this->addFlash('warning', 'Stock limit reached for ' . $product->getName());
            }
        } elseif ($action === 'decrease') {
            $cart[$id]--;
            if ($cart[$id] <= 0) {
                unset($cart[$id]);
            }
        }

        $session->set('cart', $cart);
        return $this->redirectToRoute('cart_show');
    }

    #[Route('/checkout', name: 'checkout')]
    public function checkout(Request $request, CartService $cartService, EntityManagerInterface $em): Response
    {
        $cart = $cartService->getCart();

        if ($request->isMethod('POST')) {
            // Validate stock before proceeding
            foreach ($cart as $productId => $quantity) {
                $product = $em->getRepository(Product::class)->find($productId);
                if (!$product || $quantity > $product->getStock()) {
                    $this->addFlash('danger', 'Insufficient stock for ' . ($product ? $product->getName() : 'a product') . '.');
                    return $this->redirectToRoute('cart_show');
                }
            }

            // Create Order entity
            $order = new Order();
            $order->setCustomerName('Guest');
            $order->setCustomerEmail('guest@example.com');
            $order->setStatus('Pending');
            $order->setCreatedAt(new \DateTime());

            $total = 0;

            foreach ($cart as $productId => $quantity) {
                $product = $em->getRepository(Product::class)->find($productId);
                if ($product) {
                    $subtotal = $product->getPrice() * $quantity;
                    $total += $subtotal;

                    $item = new OrderItem();
                    $item->setProduct($product);
                    $item->setQuantity($quantity);
                    $item->setSubtotal($subtotal);
                    $item->setcustomerOrder($order);

                    $em->persist($item);

                    // Deduct stock
                    $product->setStock($product->getStock() - $quantity);
                }
            }

            $order->setTotalAmount($total);
            $em->persist($order);
            $em->flush();

            $cartService->clear();

            $this->addFlash('success', 'Your order has been placed!');
            return $this->redirectToRoute('order_confirmation', ['id' => $order->getId()]);
        }

        return $this->render('cart/checkout.html.twig');
    }

    #[Route('/order/confirmation/{id}', name: 'order_confirmation')]
    public function confirmation(Order $order): Response
    {
        return $this->render('cart/confirmation.html.twig', [
            'order' => $order,
        ]);
    }
}

