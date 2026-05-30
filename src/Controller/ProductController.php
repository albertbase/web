<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Product;
use App\Form\ProductType;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Repository\ActivityLogRepository;
use App\Form\UserType;
use App\Service\ActivityLogger;
use App\Service\LogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;



#[IsGranted('ROLE_STAFF')]
#[Route('/admin')]
class ProductController extends AbstractController
{


#[Route('/menus', name: 'product_list')]
public function indexx(Request $request, ProductRepository $productRepo, CategoryRepository $categoryRepo): Response
{
    $searchTerm = $request->query->get('q');
    $selectedCategory = $request->query->get('category');

    $products = $productRepo->findByMenuFilters($searchTerm, $selectedCategory);
    $categories = $categoryRepo->findAll();

    return $this->render('product/list.html.twig', [
        'products' => $products,
        'search' => $searchTerm,
        'selectedCategory' => $selectedCategory,
        'categories' => $categories,
    ]);
}


#[Route('/products', name: 'admin_products')]
public function index(Request $request, ProductRepository $productRepo, CategoryRepository $categoryRepo): Response
{
    // ⬇️ This is where you put it
    $searchTerm = $request->query->get('search');
    $categoryFilter = $request->query->get('category');

    $products = $productRepo->findByFilters($searchTerm, $categoryFilter);

    $categories = $categoryRepo->findAll(); // for the dropdown filter

    return $this->render('product/products.html.twig', [
        'products' => $products,
        'searchTerm' => $searchTerm,
        'categoryFilter' => $categoryFilter,
        'categories' => $categories,
    ]);
}

    /**
     * Session-authenticated JSON feed for admin products live polling (main firewall).
     */
    #[Route('/products/live', name: 'admin_products_live', methods: ['GET'])]
    public function liveFeed(Request $request, ProductRepository $productRepo): JsonResponse
    {
        $searchTerm = trim((string) $request->query->get('search', ''));
        $categoryFilter = trim((string) $request->query->get('category', ''));

        $products = $productRepo->findByFilters(
            $searchTerm !== '' ? $searchTerm : null,
            $categoryFilter !== '' ? $categoryFilter : null,
        );

        return new JsonResponse([
            'success' => true,
            'products' => array_map(static function (Product $product): array {
                return [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'price' => $product->getPrice(),
                    'stock' => $product->getStock(),
                    'categoryName' => $product->getCategory()?->getName(),
                    'image' => $product->getImage(),
                ];
            }, $products),
        ]);
    }




    #[Route('/products/new', name: 'admin_products_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, LogService $logService): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setCreatedBy($this->getUser());

            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                $imageFile->move(
                    $this->getParameter('product_images_directory'),
                    $newFilename
                );

                $product->setImage($newFilename);
            }
            $product->setCreatedBy($this->getUser());
            $em->persist($product);
            $em->flush();

            $logService->logAndFlush(
            'CREATE_PRODUCT',
            'Product',
            $product->getId(),
            ['name' => $product->getName(), 'price' => $product->getPrice()]
        );

            return $this->redirectToRoute('admin_products');
        }

        return $this->render('product/product_form.html.twig', [
            'form' => $form->createView(),
            'is_edit' => false,
        ]);
    }
            #[IsGranted('PRODUCT_EDIT', subject: 'product')]
            #[Route('/products/edit/{id}', name: 'admin_products_edit', methods: ['GET', 'POST'])]
            public function edit(
                Product $product,
                Request $request,
                EntityManagerInterface $em,
                SluggerInterface $slugger,
                ActivityLogger $logger
            ): Response {

                // Voter already checked by attribute above
                // $this->denyAccessUnlessGranted('PRODUCT_EDIT', $product);

                $form = $this->createForm(ProductType::class, $product);
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {

                    $user = $this->getUser();

                    // Handle image upload
                    $imageFile = $form->get('image')->getData();
                    if ($imageFile) {
                        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                        $imageFile->move(
                            $this->getParameter('product_images_directory'),
                            $newFilename
                        );

                        $product->setImage($newFilename);
                    }

                    $em->flush();

                    $logger->log(
                        $user,
                        'update',
                        'Product',
                        $product->getId(),
                        'Updated product: ' . $product->getName()
                    );

                    return $this->redirectToRoute('admin_products');
                }

                return $this->render('product/product_form.html.twig', [
                    'form' => $form->createView(),
                    'is_edit' => true,
                    'product' => $product,
                ]);
            }


            #[Route('/products/delete/{id}', name: 'admin_products_delete', methods: ['POST'])]
            public function delete(
                int $id,
                ProductRepository $productRepository,
                EntityManagerInterface $em,
                ActivityLogger $logger
            ): Response {

                $product = $productRepository->findWithCreator($id);

                if (!$product) {
                    throw $this->createNotFoundException('Product not found.');
                }

                // Staff cannot delete — voter enforces this
                $this->denyAccessUnlessGranted('PRODUCT_DELETE', $product);

                // Prevent deletion if product has orders
                if ($product->getOrderItems()->count() > 0) {
                    $this->addFlash('danger', 'Cannot delete product — it has existing orders.');
                    return $this->redirectToRoute('admin_products');
                }

                $user = $this->getUser();

                // Log before deletion
                $logger->log(
                    $user,
                    'delete',
                    'Product',
                    $product->getId(),
                    'Deleted product: ' . $product->getName()
                );

                $em->remove($product);
                $em->flush();

                $this->addFlash('success', 'Product deleted successfully.');
                return $this->redirectToRoute('admin_products');
            }







}
