<?php

namespace App\Controller;

use App\Entity\OrderItem;
use App\Form\OrderItemType;
use App\Repository\OrderItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;


#[Route('/order/item')]
final class OrderItemController extends AbstractController
{
    #[Route(name: 'app_order_item_index', methods: ['GET'])]
    public function index(OrderItemRepository $orderItemRepository): Response
    {
        return $this->render('order_item/index.html.twig', [
            'order_items' => $orderItemRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_order_item_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $orderItem = new OrderItem();
        $form = $this->createForm(OrderItemType::class, $orderItem);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($orderItem);
            $entityManager->flush();

            return $this->redirectToRoute('app_order_item_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order_item/new.html.twig', [
            'order_item' => $orderItem,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_order_item_show', methods: ['GET'])]
    public function show(OrderItem $orderItem): Response
    {
        return $this->render('order_item/show.html.twig', [
            'order_item' => $orderItem,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_order_item_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, OrderItem $orderItem, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(OrderItemType::class, $orderItem);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_order_item_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order_item/edit.html.twig', [
            'order_item' => $orderItem,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_order_item_delete', methods: ['POST'])]
    public function delete(Request $request, OrderItem $orderItem, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$orderItem->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($orderItem);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_order_item_index', [], Response::HTTP_SEE_OTHER);
    }

    public static function getEntityFqcn(): string
    {
        return OrderItem::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('order'),
            AssociationField::new('product'),
            IntegerField::new('quantity'),
            MoneyField::new('subtotal')->setCurrency('PHP'),
        ];
    }
}
