<?php

namespace App\Controller;

use App\Entity\CakeCustomization;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class CartController extends AbstractController
{
    #[Route('/cart', name: 'cart_show')]
    public function show(CartService $cartService, ProductRepository $productRepository): Response
    {
        $summary = $this->buildCartSummary($cartService, $productRepository);

        return $this->render('cart/show.html.twig', $summary);
    }

    #[Route('/cart/add/{id}', name: 'cart_add', methods: ['POST'])]
    public function addToCart(
        int $id,
        Request $request,
        SessionInterface $session,
        ProductRepository $productRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $token = new CsrfToken('cart_add_'.$id, (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $product = $productRepository->find($id);
        if (!$product instanceof Product) {
            $this->addFlash('danger', 'Product not found.');
            return $this->redirectToRoute('app_menu');
        }

        $cart = $session->get('cart', []);
        $currentQty = (int) ($cart[$id] ?? 0);

        if ($currentQty < (int) $product->getStock()) {
            $cart[$id] = $currentQty + 1;
            $session->set('cart', $cart);
            $this->addFlash('success', 'Item added to cart.');
        } else {
            $this->addFlash('warning', 'Cannot add more. Stock limit reached.');
        }

        return $this->redirectBack($request);
    }

    #[Route('/cart/buy/{id}', name: 'cart_buy', methods: ['POST'])]
    public function buyNow(
        int $id,
        Request $request,
        SessionInterface $session,
        ProductRepository $productRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $token = new CsrfToken('cart_buy_'.$id, (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $product = $productRepository->find($id);
        if (!$product instanceof Product) {
            $this->addFlash('danger', 'Product not found.');
            return $this->redirectBack($request);
        }

        if ((int) $product->getStock() < 1) {
            $this->addFlash('warning', 'This product is out of stock.');
            return $this->redirectBack($request);
        }

        $cart = $session->get('cart', []);
        $currentQty = (int) ($cart[$id] ?? 0);

        if ($currentQty < (int) $product->getStock()) {
            $cart[$id] = $currentQty + 1;
            $session->set('cart', $cart);
        } else {
            $this->addFlash('warning', 'Cannot add more. Stock limit reached.');
            return $this->redirectToRoute('checkout');
        }

        return $this->redirectToRoute('checkout');
    }

    #[Route('/cart/update/{id}/{action}', name: 'cart_update', methods: ['POST'])]
    public function updateCart(
        string $id,
        string $action,
        Request $request,
        CartService $cartService,
        ProductRepository $productRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $token = new CsrfToken('cart_update_'.$id.'_'.$action, (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $cart = $cartService->getCart();
        if (!isset($cart[$id])) {
            $this->addFlash('warning', 'Item not found in cart.');
            return $this->redirectToRoute('cart_show');
        }

        if ($action === 'increase') {
            if (is_array($cart[$id]) && ($cart[$id]['type'] ?? null) === 'custom') {
                $cart[$id]['quantity'] = (int) $cart[$id]['quantity'] + 1;
            } else {
                $product = $productRepository->find((int) $id);
                if ($product instanceof Product && (int) $cart[$id] < (int) $product->getStock()) {
                    $cart[$id] = (int) $cart[$id] + 1;
                } else {
                    $this->addFlash('warning', 'Stock limit reached for this product.');
                }
            }
        } elseif ($action === 'decrease') {
            if (is_array($cart[$id]) && ($cart[$id]['type'] ?? null) === 'custom') {
                $cart[$id]['quantity'] = (int) $cart[$id]['quantity'] - 1;
                if ($cart[$id]['quantity'] <= 0) {
                    unset($cart[$id]);
                }
            } else {
                $cart[$id] = (int) $cart[$id] - 1;
                if ((int) $cart[$id] <= 0) {
                    unset($cart[$id]);
                }
            }
        } else {
            $this->addFlash('warning', 'Invalid cart action.');
        }

        $session = $request->getSession();
        $session->set('cart', $cart);

        return $this->redirectToRoute('cart_show');
    }

    #[Route('/cart/remove/{key}', name: 'cart_remove', methods: ['POST'])]
    public function removeFromCart(
        string $key,
        Request $request,
        CartService $cartService,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $token = new CsrfToken('cart_remove_'.$key, (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $cartService->remove($key);
        $this->addFlash('success', 'Item removed from cart.');

        return $this->redirectToRoute('cart_show');
    }

    #[Route('/checkout', name: 'checkout')]
    public function checkout(
        Request $request,
        CartService $cartService,
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $cart = $cartService->getCart();

        if ($request->isMethod('POST')) {
            $token = new CsrfToken('checkout', (string) $request->request->get('_token'));
            if (!$csrfTokenManager->isTokenValid($token)) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            if ($cart === []) {
                $this->addFlash('warning', 'Your cart is empty.');
                return $this->redirectToRoute('cart_show');
            }

            foreach ($cart as $cartKey => $item) {
                if (is_array($item) && ($item['type'] ?? null) === 'custom') {
                    continue;
                }

                $product = $productRepository->find((int) $cartKey);
                if (!$product instanceof Product || (int) $item > (int) $product->getStock()) {
                    $this->addFlash('danger', 'Insufficient stock for '.($product ? $product->getName() : 'a product').'.');
                    return $this->redirectToRoute('cart_show');
                }
            }

            $order = new Order();
            $currentUser = $this->getUser();
            $customerName = 'Guest';

            if ($currentUser instanceof User) {
                $customerName = $currentUser->getName() ?: $currentUser->getUserIdentifier();
                $order->setCreatedBy($currentUser);
            }

            $order->setCustomerName($customerName);
            $order->setCustomerPhone(null);
            $order->setStatus(Order::STATUS_PENDING);

            $total = 0.0;
            $entityManager->beginTransaction();

            try {
                foreach ($cart as $cartKey => $item) {
                    if (is_array($item) && ($item['type'] ?? null) === 'custom') {
                        $custom = $item['customization'];
                        $quantity = (int) ($item['quantity'] ?? 1);
                        $subtotal = (float) ($custom['price'] ?? 0.0) * $quantity;

                        $customization = new CakeCustomization();
                        $customization->setSize($custom['size']);
                        $customization->setFlavor($custom['flavor']);
                        $customization->setDecorations($custom['decorations'] ?? []);
                        $customization->setMessage($custom['message'] ?? null);
                        $customization->setPrice((float) $custom['price']);

                        $itemEntity = new OrderItem();
                        $itemEntity->setProduct(null);
                        $itemEntity->setCakeCustomization($customization);
                        $itemEntity->setQuantity($quantity);
                        $itemEntity->setSubtotal(round($subtotal, 2));
                        $itemEntity->setCustomerOrder($order);
                        $order->addOrderItem($itemEntity);

                        $total += $subtotal;
                        continue;
                    }

                    $product = $productRepository->find((int) $cartKey);
                    if (!$product instanceof Product) {
                        continue;
                    }

                    $entityManager->lock($product, LockMode::PESSIMISTIC_WRITE);
                    $quantity = (int) $item;

                    if ($quantity > (int) $product->getStock()) {
                        $entityManager->rollback();
                        $this->addFlash('danger', 'Insufficient stock for '.$product->getName().'.');
                        return $this->redirectToRoute('cart_show');
                    }

                    $subtotal = (float) $product->getPrice() * $quantity;
                    $total += $subtotal;

                    $itemEntity = new OrderItem();
                    $itemEntity->setProduct($product);
                    $itemEntity->setQuantity($quantity);
                    $itemEntity->setSubtotal(round($subtotal, 2));
                    $itemEntity->setCustomerOrder($order);
                    $order->addOrderItem($itemEntity);

                    $product->setStock((int) $product->getStock() - $quantity);
                }

                $order->setTotalAmount(round($total, 2));
                $entityManager->persist($order);
                $entityManager->flush();
                $entityManager->commit();
            } catch (\Throwable $exception) {
                if ($entityManager->getConnection()->isTransactionActive()) {
                    $entityManager->rollback();
                }

                $this->addFlash('danger', 'Unable to place your order right now. Please try again.');
                return $this->redirectToRoute('cart_show');
            }

            $cartService->clear();

            $this->addFlash('success', 'Your order has been placed.');
            return $this->redirectToRoute('order_confirmation', ['id' => $order->getId()]);
        }

        if ($cart === []) {
            return $this->redirectToRoute('cart_show');
        }

        $summary = $this->buildCartSummary($cartService, $productRepository);

        return $this->render('cart/checkout.html.twig', $summary);
    }

    #[Route('/order/confirmation/{id}', name: 'order_confirmation')]
    public function confirmation(Order $order): Response
    {
        return $this->render('cart/confirmation.html.twig', [
            'order' => $order,
        ]);
    }

    /**
     * @return array{cart: list<array<string, mixed>>, total: float}
     */
    private function buildCartSummary(CartService $cartService, ProductRepository $productRepository): array
    {
        $cartData = [];
        $total = 0.0;

        foreach ($cartService->getCart() as $cartKey => $item) {
            if (is_array($item) && ($item['type'] ?? null) === 'custom') {
                $quantity = (int) ($item['quantity'] ?? 1);
                $subtotal = (float) ($item['customization']['price'] ?? 0.0) * $quantity;
                $cartData[] = [
                    'key' => $cartKey,
                    'type' => 'custom',
                    'quantity' => $quantity,
                    'subtotal' => round($subtotal, 2),
                    'customization' => $item['customization'],
                ];
                $total += $subtotal;
                continue;
            }

            $product = $productRepository->find((int) $cartKey);
            if (!$product instanceof Product) {
                continue;
            }

            $quantity = (int) $item;
            $subtotal = (float) $product->getPrice() * $quantity;
            $cartData[] = [
                'key' => $cartKey,
                'type' => 'product',
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => round($subtotal, 2),
            ];
            $total += $subtotal;
        }

        return [
            'cart' => $cartData,
            'total' => round($total, 2),
        ];
    }

    private function redirectBack(Request $request): RedirectResponse
    {
        $referer = $request->headers->get('referer');
        if (is_string($referer) && $referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_menu');
    }
}
