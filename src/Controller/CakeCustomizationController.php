<?php

namespace App\Controller;

use App\Entity\CakeCustomization;
use App\Form\CakeCustomizationType;
use App\Service\CakeCustomizationService;
use App\Service\CakePreviewStorage;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CakeCustomizationController extends AbstractController
{
    #[Route('/custom-cake', name: 'custom_cake_builder')]
    public function build(
        Request $request,
        CakeCustomizationService $customizationService,
        CartService $cartService,
        CakePreviewStorage $previewStorage
    ): Response {
        $customization = new CakeCustomization();
        $customization->setSize('medium');
        $customization->setFlavor('chocolate');
        $customization->setDecorations([]);
        $customization->setQuantity(1);

        $form = $this->createForm(CakeCustomizationType::class, $customization, [
            'customization_service' => $customizationService,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted('ROLE_USER');

            $customization = $form->getData();
            $customization->setMessage(trim(strip_tags((string) $customization->getMessage())));

            $price = $customizationService->calculatePrice($customization);
            $customization->setPrice($price);

            $previewImage = $previewStorage->storeFromDataUrl(
                $request->request->getString('preview_image')
            );

            $cartService->addCustomItem([
                'size' => $customization->getSize(),
                'flavor' => $customization->getFlavor(),
                'decorations' => $customization->getDecorations(),
                'message' => $customization->getMessage(),
                'price' => $price,
                'quantity' => $customization->getQuantity() ?? 1,
                'previewImage' => $previewImage,
            ]);

            $this->addFlash('success', 'Your custom cake has been added to the cart.');
            return $this->redirectToRoute('cart_show');
        }

        return $this->render('custom_cake/create.html.twig', [
            'form' => $form->createView(),
            'sizes' => $customizationService->getSizeOptions(),
            'flavors' => $customizationService->getFlavorOptions(),
            'decorations' => $customizationService->getDecorationOptions(),
        ]);
    }
}
