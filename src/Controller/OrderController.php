<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\OrderType;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\OrderRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\OrderItem;
use App\Service\RealTimeNotificationService;
use App\Repository\ProductRepository;

#[IsGranted('ROLE_STAFF')]
final class OrderController extends AbstractController
{
    #[IsGranted('ROLE_STAFF')]
    #[Route('/admin/orders', name: 'admin_orders')]
    public function index(Request $request, OrderRepository $orderRepo): Response
    {
        $search = $request->query->get('search');
        $status = $request->query->get('status');

        $orders = $orderRepo->findByFilters($search, $status);

        return $this->render('order/orders.html.twig', [
            'orders' => $orders,
            'search' => $search,
            'status' => $status,
        ]);
    }

    /**
     * Session-authenticated JSON feed for admin live order polling (main firewall).
     */
    #[Route('/admin/orders/live', name: 'admin_orders_live', methods: ['GET'])]
    public function liveFeed(Request $request, OrderRepository $orderRepo): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $status = trim((string) $request->query->get('status', ''));
        $limit = max(1, min(200, (int) $request->query->get('limit', 100)));

        if ($status !== '' && !in_array($status, Order::ALLOWED_STATUSES, true)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid status filter.'], 400);
        }

        $orders = $orderRepo->findForAdminList(
            $search !== '' ? $search : null,
            $status !== '' ? $status : null,
            $limit,
            0
        );

        return new JsonResponse([
            'success' => true,
            'orders' => array_map(static function (Order $order): array {
                return [
                    'id' => $order->getId(),
                    'customerName' => $order->getCustomerName(),
                    'status' => $order->getStatus(),
                    'totalAmount' => $order->getTotalAmount(),
                    'createdAt' => $order->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                ];
            }, $orders),
        ]);
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route('/admin/orders/show/{id}', name: 'admin_order_show')]
    public function show(Order $order, Request $request, EntityManagerInterface $em): Response
    {
    if ($request->isMethod('POST')) {
        $newStatus = $request->request->get('status');
        $order->setStatus($newStatus);
        $em->flush();
        return $this->redirectToRoute('admin_order_show', ['id' => $order->getId()]);
    }

    return $this->render('order/order_show.html.twig', [
        'order' => $order,
    ]); 
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route('/admin/orders/{id}/update-status', name: 'admin_order_update_status', methods: ['POST'])]
    public function updateStatus(
        Request $request,
        Order $order,
        EntityManagerInterface $em,
        ActivityLogger $logger,
        RealTimeNotificationService $realTimeNotificationService,
    ): Response {
        $newStatus = $request->request->get('status');

        if (in_array($newStatus, ['Pending', 'Paid', 'Shipped', 'Delivered'])) {
            $order->setStatus($newStatus);
            $em->flush();

            $realTimeNotificationService->publishOrderStatusUpdate($order);

            $logger->log(
            $this->getUser(),
            'update',
            'Order',
            $order->getId(),
            'Updated status to: ' . $newStatus
        );

            $this->addFlash('success', 'Order status updated successfully.');
        } else {
            $this->addFlash('danger', 'Invalid status selected.');
        }

        return $this->redirectToRoute('admin_order_show', ['id' => $order->getId()]);
    }

        // #[IsGranted('ROLE_STAFF')]
       #[Route('/admin/orders/edit/{id}', name: 'admin_order_edit', methods: ['GET', 'POST'])]
public function editOrder(int $id, OrderRepository $orderRepository, Request $request, Order $order, EntityManagerInterface $em, ActivityLogger $logger): Response
{

    $order = $orderRepository->findWithCreator($id);

    $this->denyAccessUnlessGranted('ORDER_EDIT', $order);

    if ($request->isMethod('POST')) {

        // ✅ Update status
        $newStatus = $request->request->get('status');
        $order->setStatus($newStatus);

        // ✅ Update item quantities if submitted
        $submittedItems = $request->request->all('items');
        $itemsById = [];
        foreach ($order->getOrderItems() as $item) {
            $itemsById[$item->getId()] = $item;
        }

        foreach ($submittedItems as $itemId => $itemData) {
            $itemId = (int) $itemId;
            if (!isset($itemsById[$itemId])) {
                continue;
            }

            $quantity = isset($itemData['quantity']) ? max(1, (int) $itemData['quantity']) : $itemsById[$itemId]->getQuantity();
            $itemsById[$itemId]->setQuantity($quantity);
        }

        // ✅ Recalculate totals from current item quantities
        $total = 0;
        foreach ($order->getOrderItems() as $item) {
            $item->setCustomerOrder($order);
            $item->setSubtotal($item->getProduct()->getPrice() * $item->getQuantity());
            $total += $item->getSubtotal();
        }

        $order->setTotalAmount($total);

        $em->flush();

        // ✅ Log activity
        $logger->log(
            $this->getUser(),
            'update',
            'Order',
            $order->getId(),
            'Edited order details'
        );

        $this->addFlash('success', 'Order updated!');
        return $this->redirectToRoute('admin_orders');
    }

    return $this->render('order/order_edit.html.twig', [
        'order' => $order,
        'is_edit' => true,
    ]);
}


#[IsGranted('ROLE_STAFF')]
#[Route('/admin/orders/new', name: 'admin_orders_new')]
public function new(
    Request $request,
    EntityManagerInterface $em,
    ProductRepository $productRepo,
    ActivityLogger $logger,
    RealTimeNotificationService $realTimeNotificationService,
): Response
{
    $order = new Order();

    // ✅ Symfony form handles customer fields + status
    $form = $this->createForm(OrderType::class, $order);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        // ✅ Get customerName, customerPhone, status from form
        $order = $form->getData();

        $order->setCreatedBy($this->getUser());

        // ✅ Items come from custom POS UI
        $items = $request->request->all('items'); // <-- FIXED

    // ✅ Get customerName, customerPhone, status from Symfony form
$order = $form->getData();

// ✅ Items come from custom POS UI (hidden inputs)
$items = $request->request->all('items');

// ✅ If no items were submitted, prevent empty orders
if (!$items || count($items) === 0) {
    $this->addFlash('danger', 'Please add at least one product to the order.');
    return $this->redirectToRoute('admin_orders_new');
}

$total = 0;

// ✅ Process each submitted item
foreach ($items as $itemData) {

    // ✅ Find product
    $product = $productRepo->find($itemData['product_id']);
    if (!$product) {
        continue; // skip invalid product IDs
    }

    // ✅ Quantity
    $qty = isset($itemData['quantity']) ? (int) $itemData['quantity'] : 1;

    // ✅ Create OrderItem
    $orderItem = new OrderItem();
    $orderItem->setCustomerOrder($order);
    $orderItem->setProduct($product);
    $orderItem->setQuantity($qty);

    // ✅ Calculate subtotal
    $subtotal = $product->getPrice() * $qty;
    $orderItem->setSubtotal($subtotal);

    // ✅ Add to order
    $order->addItem($orderItem);

    // ✅ Add to total
    $total += $subtotal;
}

// ✅ Save total
$order->setTotalAmount($total);

// ✅ Persist order
$em->persist($order);
$em->flush();


        $order->setTotalAmount($total);

        $em->persist($order);
        $em->flush();

        $realTimeNotificationService->publishOrderCreated($order);
        $realTimeNotificationService->publishNotification(
            'New Order Received',
            sprintf('Order #%d was created for ₱%s.', $order->getId(), number_format($total, 2)),
            'success',
        );

        $logger->log(
        $this->getUser(),
        'create',
        'Order',
        $order->getId(),
        'Created a new order with total ₱' . number_format($total, 2)
    );


        return $this->redirectToRoute('admin_orders');
    }

    return $this->render('order/order_form.html.twig', [
        'form' => $form->createView(),
        'is_edit' => true,
        'products' => $productRepo->findAll(),
    ]);
}


#[IsGranted('ROLE_STAFF')]
#[Route('/admin/orders/{id}/delete', name: 'admin_order_delete', methods: ['POST'])]
public function delete(int $id, OrderRepository $orderRepository, Order $order, Request $request, EntityManagerInterface $em, ActivityLogger $logger): Response
{

    $order = $orderRepository->findWithCreator($id);
    $this->denyAccessUnlessGranted('ORDER_DELETE', $order);


    if (!$this->isCsrfTokenValid('delete_order_' . $order->getId(), $request->request->get('_token'))) {
        $this->addFlash('danger', 'Invalid CSRF token.');
        return $this->redirectToRoute('admin_orders');
    }

    // ✅ Log before deletion
    $logger->log(
        $this->getUser(),
        'delete',
        'Order',
        $order->getId(),
        'Deleted order with total ₱' . number_format($order->getTotalAmount(), 2)
    );

    $em->remove($order);
    $em->flush();

    $this->addFlash('success', 'Order deleted successfully.');

    return $this->redirectToRoute('admin_orders');
}




    }
